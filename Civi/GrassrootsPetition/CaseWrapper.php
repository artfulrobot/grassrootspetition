<?php
namespace Civi\GrassrootsPetition;

use Civi;
use Civi\Api4\GrassrootsPetitionCampaign;
use Civi\Api4\OptionValue;
use Civi\Inlay\ApiException;
use Civi\Inlay\GrassrootsPetition;
use League\CommonMark\CommonMarkConverter;
use CRM_Core_DAO;
use CRM_Grassrootspetition_ExtensionUtil as E;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Various sugar and convenience functions wrapping a Case of type GrassrootsPetition
 */
class CaseWrapper {

  /**
   * @var arrayholdstheapi3casegetoutput*/
  public $case;

  /**
   * @var arrayCacheofthecivicrm_grpet_campaignrowdata*/
  public $campaign;

  /**
   * @var arrayCacheofthepetitioncreatedactivity*/
  public $createdActivity;

  /**
   * @var \League\CommonMark\CommonMarkConverter*/
  public $markdownConverter;
  /**
   * @var Arrayofactivitytypesarrayskeyedbyname
   *
   * - Grassroots Petition created
   * - Grassroots Petition progress
   * - Grassroots Petition mailing
   * - Grassroots Petition signed
   *
   * Use getActivityTypeIDByName() which ensures init is called.
   */
  public static $activityTypesByName;

  /**
   * @var arrayStatusnametooptionvalue*/
  public static $activityStatuses;
  /**
   * @var arrayStatusnametoarrayofoptiondetails*/
  public static $caseStatusesByValue;
  /**
   * @var arrayStatusvaluetoarrayofoptiondetails*/
  public static $caseStatusesByName;
  /**
   * @var int*/
  public static $caseTypeID;
  /**
   * @var arrayofCaseWrapperobjectskeyedbyCaseIDThiswillspeedup
   * processing the same case over and over, e.g. when batch processing
   * submissions from a queue.
   */
  public static $instanceCache = [];

  /**
   * Instantiate an object from the slug
   *
   * @return CaseWrapper
   */
  public static function fromSlug(string $slug) :?CaseWrapper {
    $params = [
      'case_type_id' => 'grassrootspetition',
      'is_deleted'   => 0,
      'sequential'   => 1,
      GrassrootsPetition::getCustomFields('grpet_slug') => $slug,
    ];
    $cases = civicrm_api3('Case', 'get', $params);
    if ($cases['count'] == 1) {
      $case = new static();
      return $case->loadFromArray($cases['values'][0]);
    }
    return NULL;
  }

  /**
   * Load a (cached) instance.
   *
   * If reset is given, cache is not used.
   *
   * @return NULL|CaseWrapper
   */
  public static function fromID(int $id, bool $reset = FALSE) :?CaseWrapper {
    if (!$reset && isset(static::$instanceCache[$id])) {
      return static::$instanceCache[$id];
    }
    // This gets looked up later, if needed.
    $cases = civicrm_api3('Case', 'get', ['id' => $id]);
    if ($cases['count'] == 1) {
      $case = new static();
      $case->loadFromArray($cases['values'][$id]);
      // Cache it.
      static::$instanceCache[$id] = $case;
      return $case;
    }
    // Not found.
    return NULL;
  }

  /**
   * Create new petition.
   *
   * There's very little validation here; do that first.
   *
   * @return NULL|CaseWrapper
   */
  public static function createNew(
    int $contactID,
    string $title,
    string $campaignLabel,
    string $location,
    string $targetName,
    string $who,
    ?string $slug = NULL /* for import only */
  ) :?CaseWrapper {

    $campaign = GrassrootsPetitionCampaign::get(FALSE)
      ->setCheckPermissions(FALSE)
      ->addWhere('label', '=', $campaignLabel)
      ->execute()->first();

    if (!$campaign) {
      throw new \RuntimeException("Campaign not found '$campaignLabel' in GrassrootsPetition CaseWrapper::newFromCampaign");
    }

    $sql = "SELECT slug FROM civicrm_grpet_petition WHERE slug like %1 ORDER BY slug DESC LIMIT 1";
    if ($slug) {
      // Check slug does not exist and throw error if so.
      $dao = CRM_Core_DAO::executeQuery($sql, [1 => ["$slug", 'String']]);
      if ($dao->fetch()) {
        throw new \RuntimeException("Slug '$slug' exists: cannot import it.");
      }
    }
    else {
      //
      // Create the slug.
      //
      // This is created from the title and the slug of the campaign.
      //
      $slug = ($campaign['slug'] ? $campaign['slug'] . '/' : '') . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-');
      if (!$slug) {
        throw new \RuntimeException("Invalid slug");
      }

      // Check slug does not exist.
      $dao = CRM_Core_DAO::executeQuery($sql, [1 => ["$slug%", 'String']]);
      $maxN = NULL;
      while ($dao->fetch()) {
        if ($dao->slug === $slug) {
          $maxN = 1;
        }
        else {
          $suffix = substr($dao->slug, strlen($slug));
          if (preg_match('/-(\d+)$/', $suffix, $matches)) {
            $maxN = $matches[1] + 1;
          }
          else {
            // could be another petition - skip it.
          }
        }
      };
      $slug .= ($maxN ? "-$maxN" : '');
    }

    // Create the case.
    $campaignApiField = GrassrootsPetition::getCustomFields('grpet_campaign');
    $locationApiField = GrassrootsPetition::getCustomFields('grpet_location');
    $targetNameApiField = GrassrootsPetition::getCustomFields('grpet_target_name');
    $targetCountApiField = GrassrootsPetition::getCustomFields('grpet_target_count');
    $slugApiField = GrassrootsPetition::getCustomFields('grpet_slug');
    $whoApiField = GrassrootsPetition::getCustomFields('grpet_who');

    $caseParams = [
      'contact_id'         => $contactID,
      'creator_id'         => $contactID,
      'case_type_id'       => 'grassrootspetition',
      'status_id'          => 'grpet_Pending',
      'subject'            => $title,
      $campaignApiField    => $campaign['id'],
      $locationApiField    => $location,
      $targetNameApiField  => $targetName,
      $targetCountApiField => 100,
      $whoApiField         => $who,
      $slugApiField        => $slug,
    ];
    $case = civicrm_api3('Case', 'create', $caseParams);
    $case = static::fromID($case['id']);

    // Fix the date on the open case activity. I don't know why Case truncates it to midnight.
    $activity = $case->getPetitionCreatedActivity();
    civicrm_api3('Activity', 'create', [
      'id' => $activity['id'],
      'activity_type_id' => $activity['activity_type_id'],
      'activity_date_time' => date('Y-m-d H:i:s'),
    ]);

    if (!empty($campaign['notify_contact_id'])) {

      // Nb. the MessageTemplate API won't let you search by name(!)
      $msgTplID = civicrm_api3('MessageTemplate', 'getsingle', ['return' => 'id', 'msg_title' => 'Grassroots Petition New Petition Notification'])['id'];

      $toEmail = $campaign['notify_email'] ?? NULL;

      $from = civicrm_api3('OptionValue', 'getvalue', ['return' => "label", 'option_group_id' => "from_email_address", 'is_default' => 1]);
      if ($toEmail === NULL) {
        // Look up primary email.
        $toEmail = civicrm_api3('Email', 'get', [
          'contact_id' => $campaign['notify_contact_id'],
          'on_hold' => 0,
          'sequential' => 1,
          'return' => ['email'],
          'options' => ['sort' => "is_primary DESC"],
        ])['values'][0]['email'] ?? NULL;
        if (!$toEmail) {
          throw new ApiException(500, ['publicError' => 'Sorry, this petition is misconfigured. Please contact us. (EM2)'],
            "GrassrootsPetition: Notify contact ({$campaign['notify_contact_id']}) has no valid email, "
            . "cannot notify about new petition (CaseID " . $case->getID() . ")"
          );
        }
      }

      $params = [
        'id'             => $msgTplID,
        'from'           => $from,
        'to_email'       => $toEmail,
        'contact_id'     => $campaign['notify_contact_id'],
        // 'disable_smarty' => 1,
        'template_params' => ['campaignName' => $campaign['label']],
      ];

      try {
        civicrm_api3('MessageTemplate', 'send', $params);
      }
      catch (\Exception $e) {
        // Log silently.
        throw new ApiException(500,
          ['publicError' => 'Sorry, there was a problem notifying staff about the new petition. Please contact us. (EM3)'],
          "GrassrootsPetition: Failed to send notification of new petitoin email: " . json_encode($params) . " on case "
          . $case->getID()
        );
      }

    }

    return $case;
  }

  /**
   */
  public static function getActivityTypeIDByName(string $name) :int {
    static::init();
    return (int) static::$activityTypesByName[$name]['value'];
  }

  /**
   * Get case type ID
   */
  public static function getCaseTypeID() :int {
    if (!isset(static::$caseTypeID)) {
      static::$caseTypeID = (int) civicrm_api3('CaseType', 'get', ['name' => 'grassrootspetition'])['id'];
    }
    return static::$caseTypeID;
  }

  /**
   * Return an array of arrays with contactID and caseID that were created by the contact with the given email.
   *
   */
  public static function getPetitionsOwnedByEmail(string $email, ?int $petitionID = NULL) :?array {
    $activityTypeID = (int) static::$activityTypesByName['Grassroots Petition created']['value'];
    $petitionSql = ($petitionID > 0) ? "AND cs.id = $petitionID" : '';
    $sql = "
      SELECT e.contact_id contactID, cs.id caseID
      FROM civicrm_email e
      INNER JOIN civicrm_contact c ON e.contact_id = c.id AND c.is_deceased = 0 AND c.is_deleted = 0
      INNER JOIN civicrm_activity_contact ac ON ac.contact_id = e.contact_id AND ac.record_type_id = 3 /* Target */
      INNER JOIN civicrm_activity a ON ac.activity_id = a.id AND a.is_deleted = 0 AND a.activity_type_id = $activityTypeID
      INNER JOIN civicrm_case_activity ca ON ca.activity_id = ac.activity_id
      INNER JOIN civicrm_case cs ON cs.id = ca.case_id AND cs.is_deleted = 0
      WHERE e.email = %1 AND e.on_hold = 0 $petitionSql";
    $return = CRM_Core_DAO::executeQuery($sql, [1 => [$email, 'String']])->fetchAll();
    if ($petitionID) {
      // Only return one petition if one specified.
      $return = $return ? $return[0] : NULL;
    }
    return $return;
  }

  /**
   * Return an array of CaseWrapper objects for the given contact (and optionally case)
   */
  public static function getPetitionsOwnedByContact(int $contactID, ?int $caseID = NULL) :array {
    $params = [
      'contact_id'   => $contactID,
      'case_type_id' => 'grassrootspetition',
      'is_deleted'   => 0,
      'options' => ['limit' => 0],
    ];
    if ($caseID) {
      $params['id'] = $caseID;
    }
    $result = civicrm_api3('Case', 'get', $params)['values'] ?? [];

    $cases = [];
    foreach ($result as $caseData) {
      $case = new static();
      $case->loadFromArray($caseData);
      $cases[] = $case;
    }

    return $cases;
  }

  /**
   * Return an array of CaseWrapper objects for publicly-available petitions.
   *
   * Nb. this is a partial load of data.
   */
  public static function getPetitionsForPublic() :array {

    $customSlug = GrassrootsPetition::getCustomFields('grpet_slug');
    $customCampaign = GrassrootsPetition::getCustomFields('grpet_campaign');
    $customLocation = GrassrootsPetition::getCustomFields('grpet_location');
    $customListOrder = GrassrootsPetition::getCustomFields('grpet_list_order');

    $params = [
      'case_type_id' => 'grassrootspetition',
      'status_id' => [
        'IN' => [
        // static::$caseStatusesByName['grpet_Pending']['value'],
          static::$caseStatusesByName['Open']['value'],
        ],
      ],
      'is_deleted' => 0,
      'options' => ['limit' => 0],
      'return' => ['id', 'status_id', 'case_type_id', 'subject', $customSlug, $customCampaign, $customLocation, $customListOrder],
    ];
    $result = civicrm_api3('Case', 'get', $params)['values'] ?? [];

    $cases = [];
    foreach ($result as $caseData) {
      $case = new static();
      $case->loadFromArray($caseData);
      $cases[] = $case;
    }

    return $cases;
  }

  public function __construct() {
    static::init();
    $this->markdownConverter = new CommonMarkConverter([
      'html_input'         => 'strip',
      'allow_unsafe_links' => FALSE,
    ]);
  }

  /**
   */
  public static function init() {

    static::getCaseTypeID();

    if (!isset(static::$activityTypesByName)) {
      // Look these up once now.
      static::$activityTypesByName = OptionValue::get(FALSE)
        ->setCheckPermissions(FALSE)
        ->addWhere('option_group_id:name', '=', 'activity_type')
        ->addWhere('name', 'IN', [
          'Grassroots Petition created',
          'Grassroots Petition progress',
          'Grassroots Petition mailing',
          'Grassroots Petition signed',
        ])
        ->execute()
        ->indexBy('name')->getArrayCopy();
    }

    if (!isset(static::$activityStatuses)) {
      // Create map activity status name => option value (status_id)
      //
      // These are used for the 'Grassroots Petition progress' activity types:
      //
      // - grpet_pending_moderation  is live, but has not been checked by moderator.
      // - Completed  is live, and has been checked by moderator.
      // - Cancelled  update was removed by someone.
      // - Scheduled  N/A - reserved?
      //
      static::$activityStatuses = [];
      $r = OptionValue::get(FALSE)
        ->setCheckPermissions(FALSE)
        ->addWhere('option_group_id:name', '=', 'activity_status')
        ->addWhere('is_active', '=', 1)
        ->execute()->indexBy('name');
      // Check ones we require.
      foreach (['Completed', 'grpet_pending_moderation', 'Cancelled', 'Scheduled'] as $requiredStatus) {
        $_ = $r[$requiredStatus] ?? NULL;
        if (!$_) {
          throw new \RuntimeException("Missing required '$requiredStatus' activity status.");
        }
        static::$activityStatuses[$_['name']] = (int) $_['value'];
      }
    }

    if (!isset(static::$caseStatusesByName)) {
      // Create map activity status name => option value (status_id)
      static::$caseStatusesByName = [];
      $r = OptionValue::get(FALSE)
        ->setCheckPermissions(FALSE)
        ->addWhere('option_group_id:name', '=', 'case_status')
        ->addWhere('is_active', '=', 1)
        ->execute()->indexBy('name');
      // Check ones we require.
      foreach (['grpet_Pending', 'Open', 'grpet_Dead', 'grpet_Won'] as $requiredStatus) {
        $_ = $r[$requiredStatus] ?? NULL;
        if (!$_) {
          throw new \RuntimeException("Missing required '$requiredStatus' case status.");
        }
        $_['value'] = (int) $_['value'];
        static::$caseStatusesByName[$_['name']] = $_;
        static::$caseStatusesByValue[$_['value']] = & static::$caseStatusesByName[$_['name']];
      }
    }
  }

  /**
   * Import an array of Case data (as from api3 case.get)
   *
   * @return CaseWrapper
   */
  public function loadFromArray(array $data) :CaseWrapper {

    // Ensure status_id is an int. You never know with api3...
    $data['status_id'] = (int) $data['status_id'];

    $this->case = $data;
    // These get looked up later, if needed.
    $this->campaign = NULL;
    $this->createdActivity = NULL;
    return $this;
  }

  /**
   * Return the data needed to present the petition.
   *
   * @return array
   */
  public function getPublicData() {

    $mainImage = $this->getMainImage();

    $public = [
      'startDate'        => $this->getStartDate(),
      'status'           => $this->getCaseStatus(),
      'location'         => $this->getCustomData('grpet_location'),
      'slug'             => $this->getCustomData('grpet_slug'),
      'targetCount'      => (int) $this->getCustomData('grpet_target_count'),
      'targetName'       => $this->getCustomData('grpet_target_name'),
      'petitionTitle'    => $this->getPetitionTitle(),
      'organiser'        => $this->getCustomData('grpet_who'),
    // html?
      'petitionWhatHTML' => $this->getWhat(),
      'petitionWhyHTML'  => $this->getWhy(),
      'campaign'         => $this->getCampaignPublicName(),
      'imageUrl'         => $mainImage['imageUrl'],
      'imageAlt'         => $mainImage['imageAlt'],
      'updates'          => $this->getPublicUpdates(),
      'signatureCount'   => $this->getPetitionSigsCount(),
      'lastSigner'       => $this->getLastSigner(),
      // @todo possibly expose these on the Inlay Config?
      'consentYesText'   => 'Yes please',
      'consentNoText'    => 'No, don’t add me',
    ];

    // Is there a tweet just for this campaign?
    $public['tweet'] = $this->getCustomData('grpet_tweet_text');
    // Is the tweet copied when the petition is created... I don't think so.
    if (empty($public['tweet'])) {
      // Use default campaign tweet if there's no petition-specific one.
      $public['tweet'] = $this->getCampaign()['template_tweet'] ?? '';
    }

    return $public;
  }

  /**
   * Return value of custom field.
   *
   */
  public function getCustomData(string $field) {
    return $this->case[GrassrootsPetition::getCustomFields($field)] ?? NULL;
  }

  /**
   * Get petition HTML from the Open Case activity.
   */
  public function getPetitionHTML() {

  }

  /**
   * Get the Campaign Name
   */
  public function getCampaignAdminName() {
    $this->getCampaign()['name'];
  }

  /**
   * Get the public Campaign Name
   */
  public function getCampaignPublicName() {
    return $this->getCampaign()['label'];
  }

  /**
   * Get the Campaign data (getter for $this->campaign)
   */
  public function getCampaign() :?Array {
    if (!isset($this->campaign)) {
      $this->mustBeLoaded();

      $campaignID = $this->getCustomData('grpet_campaign');
      if (!$campaignID) {
        // e.g. new ones?
        // We don't cache this.
        return NULL;
      }

      // Look up campaign.
      $this->campaign = GrassrootsPetitionCampaign::get(FALSE)
        ->setCheckPermissions(FALSE)
        ->addWhere('id', '=', $campaignID)
        ->execute()->first();
    }
    return $this->campaign;
  }

  /**
   * Fetch the case 'client'. This would be the person who started the petition.
   *
   * @todo find out why this (and next fn) are dealing with 1-indexed arrays.
   */
  public function getClientContactID() :int {
    return (int) reset($this->case['client_id']);
  }

  /**
   * Fetch the case 'manager'. This would (also) be the person who started the petition.
   *
   * @todo find out why this (and getClientContactID) are dealing with 1-indexed arrays.
   */
  public function getManagerContactID() :int {
    return (int) reset($this->case['contact_id']);
  }

  /**
   * Fetch the petition owner; this is the case manager.
   */
  public function getOwnerContactID() :int {
    return $this->getManagerContactID();
  }

  /**
   * Get a list of machine-name download permissions
   * that have been overridden at campaign or petition level.
   *
   * @return null|Array NULL is returned if no overrides.
   */
  public function getDownloadPermissions() :?Array {
    $this->mustBeLoaded();
    // Has our petition had the defaults overridden?
    $customPerms = $this->getCustomData('grpet_download_permissions') ?? [];
    $override = array_search('override', $customPerms) !== FALSE;
    // \Civi::log()->info("getDownloadPermissions PEtition: " . json_encode([ 'customPerms' => $customPerms, 'override' => $override, ], JSON_PRETTY_PRINT));
    if ($override !== FALSE) {
      // Using overriden permissions
      $customPerms = array_values(array_diff($customPerms, ['override']));
      return $customPerms;
    }
    // Does the campaign have defaults?
    $campaign = $this->getCampaign();
    if (!empty($campaign['download_permissions'])) {
      // maybe.
      $customPerms = $campaign['download_permissions'];
      $override = array_search('override', $customPerms) !== FALSE;
      // \Civi::log()->info("getDownloadPermissions Campaign: " . json_encode([ 'customPerms' => $customPerms, 'override' => $override, ], JSON_PRETTY_PRINT));
      if ($override !== FALSE) {
        // Using overriden permissions
        $customPerms = array_values(array_diff($customPerms, ['override']));
        return $customPerms;
      }
    }

    // No, fall back to inlay defaults.
    return NULL;
  }

  /**
   * Get the list order string.
   *
   * @return string normal|priority|unlisted
   */
  public function getListOrder(): string {
    $this->mustBeLoaded();
    return $this->getCustomData('grpet_list_order') ?? 'normal';
  }

  /**
   *
   * @return null|Array NULL is returned if no overrides.
   */
  public function getMailingPermissions() :?bool {
    $this->mustBeLoaded();
    // Has our petition had the defaults overridden?
    $customPerms = $this->getCustomData('grpet_allow_mailings') ?? NULL;
    if (in_array($customPerms, ['0', '1'])) {
      return (bool) $customPerms;
    }
    // Does the campaign have defaults?
    $campaign = $this->getCampaign();
    if (isset($campaign['allow_mailings'])) {
      return ['yes' => TRUE, 'no' => FALSE][$campaign['allow_mailings']] ?? FALSE;
    }

    // No, fall back to inlay defaults.
    return NULL;
  }

  /**
   * Returns the text details of *what* the people who sign have signed up for.
   */
  public function getWhat(bool $html = TRUE) :string {
    $this->mustBeLoaded();
    $text = $this->getCustomData('grpet_what') ?? '';
    if ($html) {
      $text = $this->markdownConverter->convertToHtml($text);
    }
    return $text;
  }

  /**
   * Returns the details of *why* people should sign, this is the intro text.
   */
  public function getWhy(bool $html = TRUE) :string {
    $this->mustBeLoaded();
    $text = $this->getCustomData('grpet_why') ?? '';
    if ($html) {
      $text = $this->markdownConverter->convertToHtml($text);
    }
    return $text;
  }

  /**
   * Updates are activities of the 'Grassroots Petition progress' type
   */
  public function getPublicUpdates() {
    $this->mustBeLoaded();
    $caseID = (int) $this->case['id'];

    $updateActivityTypeID = (int) static::$activityTypesByName['Grassroots Petition progress']['value'];
    // Currently we trust the admin to publish live updates, even though we track them as pending moderation internally.
    $validStatuses = [static::$activityStatuses['Completed'], static::$activityStatuses['grpet_pending_moderation']];
    $validStatuses = implode(', ', $validStatuses);

    // Load these with SQL, as Activities and Api4 are difficult.
    $sql = "
      SELECT a.id, a.activity_type_id, a.activity_date_time `when`, a.subject, a.details,
             (SELECT COUNT(*) FROM civicrm_entity_file ef WHERE entity_table='civicrm_activity' AND entity_id = a.id ORDER BY id LIMIT 1) hasImage
        FROM civicrm_activity a
        INNER JOIN civicrm_case_activity ca ON a.id = ca.activity_id AND ca.case_id = $caseID
      WHERE a.activity_type_id = $updateActivityTypeID
        AND a.status_id IN ($validStatuses)
      ORDER BY a.activity_date_time
    ";

    $updates = [];
    $dao = CRM_Core_DAO::executeQuery($sql);
    while ($dao->fetch()) {
      $update = $dao->toArray();
      if ($update['hasImage']) {
        $this->addPublicImage($update);
      }
      // We don't need to output the type.
      unset($update['activity_type_id']);

      // Nicify the date
      $update['when'] = date('j M Y', strtotime($update['when']));

      // Clean the details. xxx todo
      $update['html'] = $update['details'];

      $updates[] = $update;
    }

    return $updates;
  }

  /**
   * Updates are activities of the 'Grassroots Petition progress' type
   *
   */
  public function getAdminUpdates() {
    $this->mustBeLoaded();
    $caseID = (int) $this->case['id'];

    $updateActivityTypeID = (int) static::$activityTypesByName['Grassroots Petition progress']['value'];
    $validStatuses = [static::$activityStatuses['Completed'], static::$activityStatuses['grpet_pending_moderation']];
    $validStatuses = implode(', ', $validStatuses);

    // Load these with SQL, as Activities and Api4 are difficult.
    $sql = "
      SELECT a.id, a.activity_type_id, a.activity_date_time, a.subject, a.details,
             (SELECT COUNT(*) FROM civicrm_entity_file ef WHERE entity_table='civicrm_activity' AND entity_id = a.id ORDER BY id LIMIT 1) hasImage
        FROM civicrm_activity a
        INNER JOIN civicrm_case_activity ca ON a.id = ca.activity_id AND ca.case_id = $caseID
      WHERE a.activity_type_id = $updateActivityTypeID
        AND a.status_id IN ($validStatuses)
      ORDER BY a.activity_date_time
    ";

    $updates = [];
    $dao = CRM_Core_DAO::executeQuery($sql);
    while ($dao->fetch()) {
      $update = $dao->toArray();
      if ($update['hasImage']) {
        $this->addPublicImage($update);
      }
      // We don't need to output the type.
      unset($update['activity_type_id']);
      $updates[] = $update;
    }

    return $updates;
  }

  /**
   * Returns a count of the signatures on a particular case.
   *
   * Optionally, check for a particular contact.
   */
  public function getPetitionSigsCount(?int $contactID = NULL) :int {
    $this->mustBeLoaded();
    // Count the 'Grassroots Petition signed' petitions.
    $signedActivityTypeID = (int) static::$activityTypesByName['Grassroots Petition signed']['value'];
    if (!$signedActivityTypeID) {
      throw new \RuntimeException("Failed to identify Grassroots Petition signed activity type. Check installation.");
    }

    $caseID = (int) $this->case['id'];
    $andIsSelectedContact = $contactID ? "AND c.id = $contactID" : '';

    // Count signed activities on this case from live contacts (i.e. exclude deleted contacts).
    $sql = "
      SELECT COUNT(*)
      FROM civicrm_activity a
      INNER JOIN civicrm_case_activity ca ON a.id = ca.activity_id AND ca.case_id = $caseID
      INNER JOIN civicrm_activity_contact ac ON ac.activity_id = a.id AND ac.record_type_id = 3 /* target */
      INNER JOIN civicrm_contact c ON ac.contact_id = c.id AND c.is_deleted = 0
      WHERE a.activity_type_id = $signedActivityTypeID
        AND a.is_deleted = 0
        $andIsSelectedContact
    ";
    $count = (int) CRM_Core_DAO::singleValueQuery($sql);

    return $count;
  }

  /**
   * Returns the name and 'ago' statement.
   *
   */
  public function getLastSigner() :array {
    $this->mustBeLoaded();
    // Count the 'Grassroots Petition signed' petitions.
    $signedActivityTypeID = (int) static::$activityTypesByName['Grassroots Petition signed']['value'];
    if (!$signedActivityTypeID) {
      throw new \RuntimeException("Failed to identify Grassroots Petition signed activity type. Check installation.");
    }

    $caseID = (int) $this->case['id'];

    // Count signed activities on this case from live contacts (i.e. exclude deleted contacts).
    $sql = "
      SELECT a.activity_date_time, c.first_name
      FROM civicrm_activity a
      INNER JOIN civicrm_case_activity ca ON a.id = ca.activity_id AND ca.case_id = $caseID
      INNER JOIN civicrm_activity_contact ac ON ac.activity_id = a.id AND ac.record_type_id = 3 /* target */
      INNER JOIN civicrm_contact c ON ac.contact_id = c.id AND c.is_deleted = 0
      WHERE a.activity_type_id = $signedActivityTypeID
        AND a.is_deleted = 0
      ORDER BY a.id DESC
      LIMIT 1
    ";
    $dao = CRM_Core_DAO::executeQuery($sql);
    if ($dao->fetch()) {
      $x = time() - strtotime($dao->activity_date_time);
      if ($x < 60) {
        $ago = "$x seconds ago";
      }
      elseif ($x < 60 * 60) {
        // up to 59 mins.
        $x = (int) ($x / 60);
        $ago = "$x minutes ago";
      }
      elseif ($x < 24 * 60 * 60) {
        // up to 23 horus
        $x = (int) ($x / 60 / 60);
        $ago = "$x hours ago";
      }
      elseif ($x < 24 * 60 * 60 * 14) {
        // up to 2 weeks, show as 'x days ago'
        $x = (int) ($x / 60 / 60 / 24);
        $ago = "$x days ago";
      }
      elseif ($x < 24 * 60 * 60 * 31 * 2) {
        // up to 2 months, show as 'x weeks ago'
        $x = (int) ($x / 60 / 60 / 24 / 7);
        $ago = "$x weeks ago";
      }
      elseif ($x < 24 * 60 * 60 * 31 * 25) {
        // up to 25 months, show as 'x months ago' (roughly)
        $x = (int) ($x / 60 / 60 / 24 / 30);
        $ago = "$x months ago";
      }
      else {
        // Over 25 months, show as years.
        $x = (int) ($x / 60 / 60 / 24 / 365);
        $ago = "$x years ago";
      }
      return ['name' => $dao->first_name, 'ago' => $ago];
    }
    return ['name' => '', 'ago' => ''];
  }

  /**
   * Returns all the data for the signatures
   */
  public function getPetitionSigs($fields = NULL) :array {
    $this->mustBeLoaded();
    // Count the 'Grassroots Petition signed' petitions.
    $signedActivityTypeID = (int) static::$activityTypesByName['Grassroots Petition signed']['value'];
    if (!$signedActivityTypeID) {
      throw new \RuntimeException("Failed to identify Grassroots Petition signed activity type. Check installation.");
    }

    $caseID = (int) $this->case['id'];

    // Get ContactIDs.
    $sql = "
      SELECT a.id activity_id
      FROM civicrm_activity a
      INNER JOIN civicrm_case_activity ca ON a.id = ca.activity_id AND ca.case_id = $caseID
      WHERE a.activity_type_id = $signedActivityTypeID
        AND a.is_deleted = 0
    ";
    /** @var \CRM_Core_DAO */
    $dao = CRM_Core_DAO::executeQuery($sql);
    $activityIDs = $dao->fetchMap('activity_id', 'activity_id');

    // Now do API4 request.
    $selects = ['activity_date_time'];
    foreach (($fields ?? ['name', 'email']) as $fieldRequested) {
      $field = [
        'name' => 'contact.display_name',
        'email' => 'email.email',
      ][$fieldRequested] ?? '';
      if ($field) {
        $selects[] = $field;
      }
    }

    $activities = \Civi\Api4\Activity::get(FALSE)
      ->addSelect(...$selects)
      ->addWhere('id', 'IN', $activityIDs)
      ->setJoin([
        ['ActivityContact AS activity_contact', TRUE, NULL, ['id', '=', 'activity_contact.activity_id'], ['activity_contact.record_type_id', '=', 3]],
        ['Contact AS contact', TRUE, NULL, ['activity_contact.contact_id', '=', 'contact.id'], ['contact.is_deleted', '=', 0], ['contact.is_deceased', '=', 0]],
        ['Email AS email', FALSE, NULL, ['email.contact_id', '=', 'contact.id'], ['email.is_primary', '=', 1]],
      ])
      ->execute()->getArrayCopy();

    return $activities;
  }

  /**
   * Return the name (or other value) of the case status from its value.
   *
   * N.b. the names are:
   * - 'grpet_Pending'
   * - 'Open'
   * - 'grpet_Dead'
   * - 'grpet_Won'
   */
  public function getCaseStatus(string $field = 'name') :string {
    $this->mustBeLoaded();
    $_ = static::$caseStatusesByValue[$this->case['status_id']][$field] ?? NULL;
    if (!$_) {
      Civi::log()->error("Could not find valid case status for case {$this->case['id']}, status id is {$this->case['status_id']} and map is " . json_encode(static::$caseStatusesByName));
    }
    return $_;
  }

  public function getStartDate() :string {
    $this->mustBeLoaded();
    $createdActivity = $this->getPetitionCreatedActivity();
    \Civi::log()->info("grpet createdActivity: " . json_encode($createdActivity));
    return date('j M Y', strtotime($createdActivity['activity_date_time']));
  }

  /**
   * Get Case ID.
   */
  public function getID() :int {
    $this->mustBeLoaded();
    return (int) $this->case['id'];
  }

  /**
   * Get Case Subject (petition title)
   */
  public function getPetitionTitle() :string {
    $this->mustBeLoaded();
    return (string) $this->case['subject'] ?? '';
  }

  /**
   * Change the case status.
   */
  public function setStatus(string $caseStatus) :CaseWrapper {
    $this->mustBeLoaded();

    $newStatusID = static::$caseStatusesByName[$caseStatus]['value'] ?? NULL;
    if (!$newStatusID) {
      throw new \InvalidArgumentException("'$caseStatus' is invalid case status");
    }
    if ($this->case['status_id'] == $newStatusID) {
      // Nothing to do.
      return $this;
    }
    civicrm_api3('case', 'create', [
      'id'        => $this->case['id'],
      'status_id' => $newStatusID,
    ]);
    $this->case['status_id'] = $newStatusID;

    return $this;
  }

  /**
   */
  public function setWhy(string $value) :CaseWrapper {
    return $this->setCustomData(['grpet_why' => $value]);
  }

  /**
   */
  public function setWhat(string $value) :CaseWrapper {
    return $this->setCustomData(['grpet_what' => $value]);
  }

  /**
   */
  public function setWho(string $value) :CaseWrapper {
    return $this->setCustomData(['grpet_who' => $value]);
  }

  /**
   * Set value of custom fields
   */
  public function setCustomData(array $fieldnameToValue) :CaseWrapper {
    $this->mustBeLoaded();
    $params = [
      'id' => $this->case['id'],
    ];
    foreach ($fieldnameToValue as $field => $value) {
      $apiName = GrassrootsPetition::getCustomFields($field);
      if (($this->case[$apiName] ?? '') !== $value) {
        $params[$apiName] = $value;
        // update cache.
        $this->case[$apiName] = $value;
      }
    }

    // Only update if something is changing.
    if (count($params) > 1) {
      civicrm_api3('Case', 'create', $params);
    }
    return $this;
  }

  /**
   * Change the case status.
   */
  public function setPetitionTitle(string $title) :CaseWrapper {
    $this->mustBeLoaded();
    $title = trim($title);
    if (!$title) {
      throw new \InvalidArgumentException("Petition title cannot be empty");
    }
    if ($this->case['subject'] !== $title) {
      civicrm_api3('case', 'create', [
        'id'      => $this->case['id'],
        'subject' => $title,
      ]);
      $this->case['subject'] = $title;
    }
    return $this;
  }

  /**
   * Add a signed petition activity to the case for the given contact.
   *
   * Nb we assume some validation/filtering has been done on the input.
   *
   * @param int $contactID
   * @param array $data containing these keys:
   *   - location       The URL of the page the petition was on, inc. query and hash
   *   - optin          If 'yes' record they opted in to updates.
   *                    Nb. storing here is duplication for the convenience of reporting.
   *   - comment        Public comment
   *   - activity_date_time (optional)
   *
   * @return int Activity ID created.
   */
  public function addSignedPetitionActivity(int $contactID, array $data) :int {

    $optin = GrassrootsPetition::getCustomFields('grpet_sig_optin');
    $activityCreateParams = [
      'activity_type_id'     => static::$activityTypesByName['Grassroots Petition signed']['value'],
      'target_id'            => $contactID,
      'source_contact_id'    => $contactID,
      'subject'              => $this->case['subject'], /* Copy case subject (petition title)  */
      'status_id'            => 'Completed',
      'case_id'              => $this->case['id'],
      'location'             => $data['location'],
      $optin                 => ($data['optin'] === 'yes') ? 1 : 0,
    ];
    if (!empty($data['activity_date_time'])) {
      $activityCreateParams['activity_date_time'] = $data['activity_date_time'];
    }
    if (!empty($data['comment'])) {
      $activityCreateParams['details'] = $data['comment'];
    }

    $result = civicrm_api3('Activity', 'create', $activityCreateParams);

    return (int) $result['id'];
  }

  /**
   * Add an update activity.
   *
   * Nb we assume some validation/filtering has been done on the input.
   *
   * @return int Activity ID created.
   */
  public function addUpdateActivity(int $contactID, string $subject, string $text, ?string $timestamp = NULL) :int {

    $activityCreateParams = [
      'activity_type_id'     => static::$activityTypesByName['Grassroots Petition progress']['value'],
      'target_id'            => $contactID,
      'source_contact_id'    => $contactID,
      'subject'              => $subject, /** empty ? */
      'status_id'            => 'grpet_pending_moderation',
      'case_id'              => $this->case['id'],
      'details'              => $text,
    ];
    if (!empty($timestamp)) {
      $activityCreateParams['activity_date_time'] = $timestamp;
    }
    Civi::log()->info("addUpdateActivity: " . json_encode($activityCreateParams + ['got' => static::$activityTypesByName]));

    $result = civicrm_api3('Activity', 'create', $activityCreateParams);

    return (int) $result['id'];
  }

  /**
   */
  public function addImageToUpdateActivityFromData(int $activityID, string $imageData, string $imageFileType) {

    $filename = "petition_" . $this->case['id'] . "_update_{$activityID}_image";
    if ($imageFileType === 'image/jpeg') {
      $filename .= '.jpg';
    }
    elseif ($imageFileType === 'image/png') {
      $filename .= '.png';
    }
    else {
      throw new \InvalidArgumentException("Unsupported image type '$imageFileType'");
    }

    // Get first attachment for this activity.
    $original = civicrm_api3('Attachment', 'get', [
      'return' => 'id',
      'entity_table' => 'civicrm_activity',
      'entity_id' => $activityID,
      'options' => ['limit' => 1, 'sort' => 'id'],
    ]);
    if (!empty($original['id'])) {
      civicrm_api3('Attachment', 'delete', [
        'id' => $original['id'],
      ]);
    }

    civicrm_api3('Attachment', 'create', [
      'entity_table' => 'civicrm_activity',
      'entity_id' => $activityID,
      'name' => $filename,
      'mime_type' => $imageFileType,
      'content' => $imageData,
    ]);

  }

  /**
   * Handle consent.
   *
   * This is only called if consent was actively opted in to.
   *
   * @return int Activity ID created.
   */
  public function recordConsent(int $contactID, array $data, ?int $groupID) :void {

    if (class_exists('CRM_Gdpr_CommunicationsPreferences_Utils')) {
      \CRM_Gdpr_CommunicationsPreferences_Utils::createCommsPrefActivity($contactID,
        [
          'activity_source' => "<p>Opted-in via Grassroots Petition "
          . htmlspecialchars($this->getPetitionTitle())
          . ' on page '
          . htmlspecialchars($data['location'])
          . '</p>',
        ]);
    }

    // todo extract this from the petition side of things; use a hook.
    // Add them to the newsletter

    if ($groupID) {
      list($total, $added, $notAdded) = \CRM_Contact_BAO_GroupContact::addContactsToGroup([$contactID], $groupID, 'Web', 'Added');
    }

    // Add them to the email consent group
    $emailConsentGroup = \Civi\Api4\Group::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'consent_all_email')
      ->execute()
      ->first()['id'] ?? NULL;
    if (!$emailConsentGroup) {
      Civi::log()->warning("Failed to find consent_all_email Group; was going to add contact $contactID into it as they signed up.");
    }
    else {
      list($total, $added, $notAdded) = \CRM_Contact_BAO_GroupContact::addContactsToGroup([$contactID], $emailConsentGroup, 'Web', 'Added');
    }

    if (!empty($data['phone'])) {
      // Add them to the phone consent group, if phone number given
      $phoneConsentGroup = \Civi\Api4\Group::get(FALSE)
        ->addSelect('id')
        ->addWhere('name', '=', 'consent_all_phone')
        ->execute()
        ->first()['id'] ?? NULL;
      if (!$phoneConsentGroup) {
        Civi::log()->warning("Failed to find consent_all_phone Group; was going to add contact $contactID into it as they signed up.");
      }
      else {
        list($total, $added, $notAdded) = \CRM_Contact_BAO_GroupContact::addContactsToGroup([$contactID], $phoneConsentGroup, 'Web', 'Added');
      }
    }

    // Add them to the group for this petition
    // todo

  }

  /**
   * Returns absolute file path or url to an image.
   *
   * A URL is only returned if the file exists, however the path is always returned.
   *
   * If $activityID is 0 then a temp file name is returned. NULL means main
   * image, not one related to an update activity.
   *
   * Files are named:
   * - <petitionHash>-main.jpg
   * - <petitionHash>-update-<activityID>.jpg
   */
  public function getFile(string $pathOrUrl, ?int $activityID = NULL) :?string {
    $petitionHash = substr(sha1(CIVICRM_SITE_KEY . $this->case['id']), 0, 8);

    if ($activityID === 0) {
      if ($pathOrUrl !== 'path') {
        throw new \RuntimeException("requires 'path' if temp file requested");
      }
      $unique = substr(sha1(CIVICRM_SITE_KEY . 'grpe tempfile'), 0, 8);
      $fileName = "$petitionHash-temp-$unique.jpg";
    }
    elseif ($activityID !== NULL) {
      $fileName = "$petitionHash-update-$activityID.jpg";
    }
    else {
      $fileName = "$petitionHash-main.jpg";
    }

    $filePath = Civi::paths()->getPath("[civicrm.files]/grassrootspetition-images/$fileName");
    if (!file_exists($filePath) && $pathOrUrl === 'url') {
      // URL requested, but file does not exist.
      return NULL;
    }

    $path = "[civicrm.files]/grassrootspetition-images/$fileName";
    if ($pathOrUrl === 'path') {
      return Civi::paths()->getPath($path);
    }
    elseif ($pathOrUrl === 'url') {
      return Civi::paths()->getUrl($path, 'absolute');
    }
    else {
      throw new \Exception(__FUNCTION__ . " requires pathOrUrl to be path|url. '$pathOrUrl' given.");
    }
  }

  /**
   * Creates a temporary rescaled image file and returns its path, if successful.
   */
  public function createPublicImage(array $attachment) :?string {
    $attachmentID = ((int) $attachment['id'] ?? 0) ?: '<missing ID!>';

    switch ($attachment['mime_type'] ?? '') {
      case 'image/jpeg':
        $expectedExtensionRegex = '/\.jpe?g$/';
        $imageLoadFunction = 'imagecreatefromjpeg';
        $expectedImageType = IMAGETYPE_JPEG;
        break;

      case 'image/png':
        $expectedExtensionRegex = '/\.png$/';
        $imageLoadFunction = 'imagecreatefrompng';
        $expectedImageType = IMAGETYPE_PNG;
        break;

      default:
        throw new \RuntimeException("Attachment $attachmentID is not image/jpeg or image/png type.");
    }

    if (!preg_match($expectedExtensionRegex, $attachment['name'] ?? '')) {
      // File is not of correct extension.
      throw new \RuntimeException("Attachment $attachmentID does not have expected file extension for a $attachment[mime_type] file.");
    }

    $src = $attachment['path'] ?? '';
    if (!$src || !file_exists($src) || !is_readable($src)) {
      throw new \RuntimeException("Attachment $attachmentID file $src is unreadable/non-existent.");
    }
    // Limit processing to 12MB files. Seems reasonable.
    if (filesize($src) > 1024 * 1024 * 12) {
      throw new \RuntimeException("Attachment $attachmentID does $src too big to process.");
    }
    // OK, we have something that could be a JPEG.
    if (!extension_loaded('gd') || !function_exists('gd_info')) {
      throw new \RuntimeException("Attachment $attachmentID can’t be processed as gd is not available.");
    }

    $tempFile = $this->getFile('path', 0);
    $imgProperties = getimagesize($src);
    if ($imgProperties[2] !== $expectedImageType) {
      throw new \RuntimeException("Attachment $attachmentID file $src is not a $attachment[mime_type] file according to GD");
    }
    $srcImage = $imageLoadFunction($src);
    // Calculate new size.
    // We need images that are 1200px wide.
    $newW = 1200;
    // This ratio is for twitter and facebook (Feb 2021) but Twitter
    // (summary_large_image) will chop the top and bottom 15px.
    $ratio = 1200 / 628;

    $maxH = (int) ($newW / $ratio);
    $newH = (int) ($imgProperties[1] * $newW / $imgProperties[0]);
    $offsetY = 0;
    // copy full width
    $copyW = $imgProperties[0];
    // copy full height
    $copyH = $imgProperties[1];
    if ($newH > $maxH) {
      // Image is taller than ratio.
      // We will take a crop from the middle.
      $offsetY = (int) (($imgProperties[1] - $imgProperties[0] / $ratio) / 2);
      $copyH = (int) ($copyW / $ratio);
      // Restrict new height.
      $newH = $maxH;
    }
    $destImg = imagecreatetruecolor($newW, $newH);
    imagecopyresampled(
      $destImg, $srcImage,
      0, 0, /* dest x, y */
      0, $offsetY, /* src x, y */
      $newW, $newH, /* dest w, h */
      $imgProperties[0], $copyH);
    // Save file: always using jpeg.
    imagejpeg($destImg, $tempFile);
    // move_uploaded_file($image, $pathToImages.$imageName);
    return $tempFile;
  }

  /**
   * Return an array with the public URL (or NULL) and ALT text for the main image.
   *
   * This will create images that don't exist, and it will delete imags that do but shouldn't!
   */
  public function getMainImage() :array {
    $openCase = $this->getPetitionCreatedActivity();
    $this->addPublicImage($openCase);
    return $openCase;
  }

  /**
   */
  public function setMainImageFromImageData($imageData, $imageFileType) {
    $activity = $this->getPetitionCreatedActivity();

    $filename = "petition_" . $this->case['id'] . "_main_image.";
    if ($imageFileType === 'image/jpeg') {
      $filename .= 'jpg';
    }
    elseif ($imageFileType === 'image/png') {
      $filename .= 'png';
    }
    else {
      throw new \InvalidArgumentException("Unsupported image type '$imageFileType'");
    }

    // Get first attachment for this activity.
    $original = civicrm_api3('Attachment', 'get', [
      'return' => 'id',
      'entity_table' => 'civicrm_activity',
      'entity_id' => $activity['id'],
      'options' => ['limit' => 1, 'sort' => 'id'], /* this doesnot work */
    ]);
    foreach ($original['values'] ?? [] as $item) {
      Civi::log()->debug("Found previous attachment $item[id], on activity $activity[id]. Deleting attachment now.");
      civicrm_api3('Attachment', 'delete', ['id' => $item['id']]);

      $filePath = $this->getFile('path', NULL);
      if (file_exists($filePath)) {
        Civi::log()->debug("Deleting original public file $filePath so new one shines through.");
        unlink($filePath);
      }
    }

    Civi::log()->debug("Creating new attachment on activity $activity[id] image type $imageFileType file $filename");
    civicrm_api3('Attachment', 'create', [
      'entity_table' => 'civicrm_activity',
      'entity_id' => $activity['id'],
      'name' => $filename,
      'mime_type' => $imageFileType,
      'content' => $imageData,
    ]);

  }

  /**
   * Look up the Grassroots Petition created activity.
   *
   * This is cached.
   */
  public function getPetitionCreatedActivity() :array {
    if (!isset($this->createdActivity)) {
      // Get open case activity.
      $openCase = civicrm_api3('Activity', 'get', [
        'sequential'   => 1,
        'case_id' => $this->case['id'],
        'activity_type_id' => static::$activityTypesByName['Grassroots Petition created']['value'],
        'return' => ['id', 'status_id', 'activity_type_id', 'activity_date_time'],
      ]);
      if (empty($openCase['id'])) {
        // This is an error!
        throw new \RuntimeException("Case {$this->case['id']} has no Grassroots Petition created activity.");
      }
      $this->createdActivity = $openCase['values'][0];
    }
    return $this->createdActivity;
  }

  /**
   * Get a smart group set up to track petition signatures on this case,
   * creating it if it does not exist.
   *
   * Nb. the group and the saved search each have the machine name
   * grassrootspetition_<CASE_ID>_mailing_group
   *
   * @return int the ID of the group.
   */
  public function getSignerMailingList() :int {
    $groupName = "grassrootspetition_" . $this->case['id'] . "_mailing_group";
    $groupID = \Civi\Api4\Group::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', $groupName)
      ->execute()
      ->first()['id'] ?? 0;
    if (!$groupID) {

      // Group not found. Create it now.
      // @todo - should use searchkit here I think. We're looking for contacts who have done an activity of known type and (subject ?) and case_id, AND opted-in (where is this stored - custom field on activity)

      $api = [
        "version" => 4,
        "select" => ["id", "display_name", "Contact_ActivityContact_Activity_01.activity_date_time", "Contact_ActivityContact_Activity_01.subject"],
        "orderBy" => [],
        "where" => [["Contact_ActivityContact_Activity_01.grpet_signature.grpet_sig_optin:name", "=", TRUE]],
        "groupBy" => [],
        "join" => [
          [
            "Activity AS Contact_ActivityContact_Activity_01", "INNER", "ActivityContact", ["id", "=", "Contact_ActivityContact_Activity_01.contact_id"],
            ["Contact_ActivityContact_Activity_01.record_type_id:name", "=", "\"Activity Targets\""],
            ["Contact_ActivityContact_Activity_01.activity_type_id:name", "=", "\"Grassroots Petition signed\""],
          ],
          [
            "Case AS Contact_ActivityContact_Activity_01_Activity_CaseActivity_Case_01", "INNER", "CaseActivity",
              ["Contact_ActivityContact_Activity_01.id", "=", "Contact_ActivityContact_Activity_01_Activity_CaseActivity_Case_01.activity_id"],
              ["Contact_ActivityContact_Activity_01_Activity_CaseActivity_Case_01.id", "=", $this->case['id']],
          ],
        ],
        "having" => [],
      ];
      $groupTitle = E::ts("Grassroots Petition %1 email updates", [1 => $this->case['id']]);
      // ->addValue('public',

      // Create a saved search for this.
      // First delete any saved search we already had with this name - there
      // shouldn't really be one there, so this is a just-in-case.
      \Civi\Api4\SavedSearch::delete(FALSE)->addWhere('name', '=', $groupName)->execute();
      $savedSearchID = \Civi\Api4\SavedSearch::create(FALSE)
        ->addValue('name', $groupName)
        ->addValue('label', $groupTitle)
        ->addValue('api_entity', 'Contact')
        ->addValue('api_params', $api)
        ->execute()->first()['id'];

      // Now create a smart group.
      $groupID = \Civi\Api4\Group::create(FALSE)
        ->addValue('name', $groupName)
        ->addValue('title', $groupTitle)
        ->addValue('description', E::ts('People who signed this petition and opted in to updates.'))
        ->addValue('saved_search_id', $savedSearchID)
        ->addValue('is_active', TRUE)
        ->addValue('api_params', $api)
        ->addValue('visibility', 'User and User Admin Only')
      // Mailing group.
        ->addValue('group_type', [2])
        ->addValue('frontend_title', E::ts("Updates from: %1", [1 => $this->getPetitionTitle()]))
        // Internal description is ok. ->addValue('frontend_description', E::ts(""))
        ->execute()->first()['id'];
    }

    return (int) $groupID;
  }

  /**
   * Assert that the case is loaded; used by public getters.
   */
  protected function mustBeLoaded() {
    if (empty($this->case)) {
      throw new \RuntimeException("CaseWrapper: no case loaded.");
    }
  }

  /**
   * Adds imageUrl and imageAlt to the activity array. NULL if no image.
   *
   * Uses the following in $activity:
   * - activity_type_id
   * - id
   */
  protected function addPublicImage(array &$activity) {

    $updateActivityID = NULL;
    if (empty($activity['activity_type_id'])) {
      Civi::log()->error("Case #" . ($this->case['id'] ?? '??') . " called addPublicImage with an array that's missing activity_type_id: " . json_encode($activity));
      $activity['imageUrl'] = NULL;
      $activity['imageAlt'] = NULL;
      return;
    }
    if ($activity['activity_type_id'] == static::$activityTypesByName['Grassroots Petition progress']['value']) {
      // Is an update activity
      $updateActivityID = $activity['id'];
    }

    // Get first attachment for this activity.
    $attachment = civicrm_api3('Attachment', 'get', [
      'entity_table' => 'civicrm_activity',
      'entity_id'    => $activity['id'],
      'options'      => ['limit' => 1, 'sort' => 'id'],
      'sequential'   => 1,
    ])['values'][0] ?? NULL;

    $filePath = $this->getFile('path', $updateActivityID);

    if (empty($attachment)) {
      // No image.
      if (file_exists($filePath)) {
        unlink($filePath);
        Civi::log()->info("GrassrootsPetition: Deleted file '$filePath' since Case {$this->case['id']} no longer has an image file attached to activity $activity[id].");
      }
      $activity['imageUrl'] = NULL;
      $activity['imageAlt'] = NULL;

      // See if we can use the default campaign image here?
      $campaign = $this->getCampaign();
      if (!empty($campaign['template_image_url'])) {
        $activity['imageUrl'] = $campaign['template_image_url'];
        $activity['imageAlt'] = $campaign['template_image_alt'] ?? '';
      }
      return;
    }

    if (!file_exists($filePath)) {
      // File does not exist.
      Civi::log()->info("trying to create public image for " . json_encode($attachment, JSON_PRETTY_PRINT));
      try {
        $tempFile = $this->createPublicImage($attachment);
      }
      catch (\Exception $e) {
      }
      if ($tempFile) {
        rename($tempFile, $filePath);
        Civi::log()->info("GrassrootsPetition: Created file '$filePath' for Case {$this->case['id']} on activity $activity[id]");
      }
    }

    $activity['imageUrl'] = $this->getFile('url', $updateActivityID);
    $activity['imageAlt'] = $attachment['description'] ?? '';
  }

}
