<?php
namespace Civi\GrassrootsPetition;

use Civi\Inlay\GrassrootsPetition;
use CRM_Core_DAO;
use Civi\Api4\OptionValue;
use Civi\Api4\GrassrootsPetitionCampaign;
use Civi;

/**
 * Various sugar and convenience functions wrapping a Case of type GrassrootsPetition
 */
class CaseWrapper {

  /** @var array holds the api3 case.get output */
  public $case;

  /** @var array Cache of the civicrm_grpet_campaign row data */
  public $campaign;

  /** @var array Cache of the petition created activity */
  public $createdActivity;

  /** @var Array */
  public static $activityTypesByName;

  /** @var array Status name to option value */
  public static $activityStatuses;
  /** @var array Status name to option value */
  public static $caseStatuses;
  public static $caseTypeID;
  /**
   * @var array of CaseWrapper objects keyed by Case ID. This will speed up
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
    $inlay = new GrassrootsPetition();
    $params = [ $inlay->getCustomFields('grpet_slug') => $slug, 'sequential' => 1 ];
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
  public static function fromID(int $id, bool $reset=FALSE) :?CaseWrapper {
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
    ?string $slug
  ) :?CaseWrapper {

    $campaign = GrassrootsPetitionCampaign::get(FALSE)
      ->setCheckPermissions(FALSE)
      ->addWhere('label', '=', $campaignLabel)
      ->execute()->first();

    if (!$campaign) {
      throw new \RuntimeException("Campaign not found '$campaignLabel' in GrassrootsPetition CaseWrapper::newFromCampaign");
    }

    // Create the slug.
    if ($slug) {
      $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($slug)), '-');
    }
    else {
      $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-');
    }

    // Check slug does not exist.
    $sql = "SELECT slug FROM civicrm_grpet_petition WHERE slug like %1 ORDER BY slug DESC LIMIT 1";
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

    // Create the case.
    $inlay = new GrassrootsPetition();
    $campaignApiField = $inlay->getCustomFields('grpet_campaign');
    $locationApiField = $inlay->getCustomFields('grpet_location');
    $targetNameApiField = $inlay->getCustomFields('grpet_target_name');
    $targetCountApiField = $inlay->getCustomFields('grpet_target_count');
    $slugApiField = $inlay->getCustomFields('grpet_slug');
    $whoApiField = $inlay->getCustomFields('grpet_who');

    $caseParams = [
      'contact_id'         => $contactID,
      'creator_id'         => $contactID,
      'case_type_id'       => 'grassrootspetition',
      'status_id'          => 'grpet_Pending',
      'subject'            => $title,
      'details'            => $campaign['template'],
      $campaignApiField    => $campaign['id'],
      $locationApiField    => $location,
      $targetNameApiField  => $targetName,
      $targetCountApiField => 100,
      $whoApiField         => $who,
      $slugApiField        => $slug,
    ];
    print "Create case with: " . json_encode($caseParams, JSON_PRETTY_PRINT) . "\n";
    $case = civicrm_api3('Case', 'create', $caseParams);

    return static::fromID($case['id']);
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

  public function __construct() {
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
        static::$activityStatuses[$_['name']] = $_['value'];
      }
    }

    if (!isset(static::$caseStatuses)) {
      // Create map activity status name => option value (status_id)
      static::$caseStatuses = [];
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
        static::$caseStatuses[$_['name']] = $_['value'];
      }
    }
  }
  /**
   * Import an array of Case data (as from api3 case.get)
   *
   * @return CaseWrapper
   */
  public function loadFromArray($data) {
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
      'status'           => $this->getCaseStatus(),
      'location'         => $this->getCustomData('grpet_location'),
      'slug'             => $this->getCustomData('grpet_slug'),
      'targetCount'      => $this->getCustomData('grpet_target_count'),
      'targetName'       => $this->getCustomData('grpet_target_name'),
      'tweet'            => $this->getCustomData('grpet_tweet_text'),
      'petitionTitle'    => $this->getPetitionTitle(),
      'organiser'        => $this->getCustomData('grpet_who'),
      'petitionWhatHTML' => $this->getWhat(), // html?
      'petitionWhyHTML'  => $this->getWhy(),
      'campaign'         => $this->getCampaignPublicName(),
      'imageUrl'         => $mainImage['url'],
      'imageAlt'         => $mainImage['alt'],
      'updates'          => $this->getPetitionUpdates(),
      'signatureCount'   => $this->getPetitionSigsCount(),
      // @todo expose these on the Inlay Config
      'consentIntroHTML' => '<p>Get emails about this campaign and from People & Planet on our current and future projects, campaigns and appeals. There’s a link to unsubscribe at the bottom of each email update. <a href="https://peopleandplanet.org/privacy">Privacy Policy</a></p>',
      'consentYesText'   => 'Yes please',
      'consentNoText'    => 'No, don’t add me',
      'consentNoWarning' => 'If you’re not already subscribed you won’t hear about the success (or otherwise!) of this campaign. Sure?',
      'thanksShareAsk'   => '<h2>Thanks, please share this petition</h2><p>Thanks for signing. Can you share to help amplify your voice?</p>',
      'thanksDonateAsk'  => '<h2>Thanks, can you donate?</h2><p>Can you chip in to help People &amp; Planet’s campaigns?</p><p><a class="button primary" href="/donate">Donate</a></p>',
    ];

    return $public;
  }

  /**
   * Return value of custom field.
   *
   */
  public function getCustomData(string $field) {
    $inlay = new GrassrootsPetition();
    return $this->case[$inlay->getCustomFields($field)] ?? NULL;
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
   * Returns the text details of *what* the people who sign have signed up for.
   */
  public function getWhat() :string {
    $this->mustBeLoaded();
    return $this->getCustomData('grpet_what');
  }
  /**
   * Returns the details of *why* people should sign, this is the intro text.
   */
  public function getWhy() :string {
    $this->mustBeLoaded();
    return $this->getCustomData('grpet_why');
  }
  /**
   * Updates are activities of the 'Grassroots Petition progress' type
   */
  public function getPetitionUpdates() {
    $this->mustBeLoaded();
    $caseID = (int) $this->case['id'];

    $updateActivityTypeID = (int) static::$activityTypesByName['Grassroots Petition update']['value'];
    $validStatuses = [static::$activityStatuses['Completed']];
    // @todo allow drafts?
    $validStatuses = implode(', ', $validStatuses);

    // Load these with SQL, as Activities and Api4 are difficult.
    $sql = "
      SELECT a.id, a.activity_date_time, a.subject, a.details
        FROM civicrm_activity a
        INNER JOIN civicrm_case_activity ca ON a.id = ca.activity_id AND ca.case_id = $caseID
      WHERE a.activity_type_id = $updateActivityTypeID
        AND a.status_id IN ($validStatuses)
      ORDER BY a.activity_date_time
    ";

    $updates = [];
    $dao = CRM_Core_DAO::executeQuery($sql);
    while ($dao->fetch()) {
      $updates[] = $dao->toArray();
    }
    // @todo images (attachments)

    return $updates;
  }
  /**
   * Returns a count of the signatures on a particular case.
   *
   * Optionally, check for a particular contact.
   */
  public function getPetitionSigsCount(?int $contactID=NULL) :int {
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

    // $count+= rand(23,76); // xxx todo
    return $count;
  }
  /**
   * Return the name of the case status from its value.
   *
   * N.b. the names are:
   * - 'grpet_Pending'
   * - 'Open'
   * - 'grpet_Dead'
   * - 'grpet_Won'
   */
  public function getCaseStatus() :string {
    $this->mustBeLoaded();
    return array_flip(static::$caseStatuses)[$this->case['status_id']];
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
    if (empty(static::$caseStatuses[$caseStatus])) {
      throw new \InvalidArgumentException("'$caseStatus' is invalid case status");
    }
    $newStatusID = static::$caseStatuses[$caseStatus];
    if ($this->case['status_id'] == $newStatusID) {
      // Nothing to do.
      return $this;
    }
    civicrm_api3('case', 'create', [
      'id'        => $this->case['id'],
      'status_id' => $newStatusID,
    ]);
    $this->case['status_id'] = static::$caseStatuses[$caseStatus];
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
    $inlay = new GrassrootsPetition();
    $params = [
      'id' => $this->case['id'],
    ];
    foreach ($fieldnameToValue as $field => $value) {
      $apiName = $inlay->getCustomFields($field);
      $params[$apiName] = $value;
      // update cache.
      $this->case[$apiName] = $value;
    }

    civicrm_api3('Case', 'create', $params);
    return $this;
  }
  /**
   * Add a signed petition activity to the case for the given contact.
   *
   * @return int Activity ID created.
   */
  public function addSignedPetitionActivity(int $contactID, array $data) :int {

    $activityCreateParams = [
      'activity_type_id'     => static::$activityTypesByName['Grassroots Petition signed']['value'],
      'target_id'            => $contactID,
      'source_contact_id'    => $contactID,
      'subject'              => $this->case['subject'], /* Copy case subject (petition title)  */
      'status_id'            => 'Completed',
      'case_id'              => $this->case['id'],
      'location'             => $data['location'],
      // xxx move this to custom field.
      'details'              => ($data['optin'] === 'yes') ? '<p>Opted in to updates</p>' : '<p>Did not opt in</p>',
    ];
    $result = civicrm_api3('Activity', 'create', $activityCreateParams);

    return (int) $result['id'];
  }
  /**
   * Handle consent.
   *
   * This is only called if consent was actively opted in to.
   *
   * @return int Activity ID created.
   */
  public function recordConsent(int $contactID, array $data) :void {

    if (class_exists('CRM_Gdpr_CommunicationsPreferences_Utils')) {
      \CRM_Gdpr_CommunicationsPreferences_Utils::createCommsPrefActivity($contactID,
        ['activity_source' => "<p>Opted-in via Grassroots Petition "
        . htmlspecialchars($this->getPetitionTitle())
        . ' on page '
        . htmlspecialchars($data['location'])
        . '</p>'
        ]);
    }


    // todo extract this from the petition side of things; use a hook.
    // Add them to the P&P newsletter
    $groupID = 62; // xxx remove hard coded value!
    list($total, $added, $notAdded) = \CRM_Contact_BAO_GroupContact::addContactsToGroup([$contactID], $groupID, 'Web', 'Added');

    // Add them to the email consent group
    $emailConsentGroup = \Civi\Api4\Group::get(FALSE)
        ->addSelect('id')
        ->addWhere('name', '=', 'consent_all_email')
        ->execute()
        ->first()['id'] ?? NULL;
    if (!$emailConsentGroup) {
      Civi::log()->error("Failed to find consent_all_email Group; was going to add contact $contactID into it as they signed up.");
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
        Civi::log()->error("Failed to find consent_all_phone Group; was going to add contact $contactID into it as they signed up.");
      }
      else {
        list($total, $added, $notAdded) = \CRM_Contact_BAO_GroupContact::addContactsToGroup([$contactID], $phoneConsentGroup, 'Web', 'Added');
      }
    }
    // Add them to the group for this petition

  }
  /**
   * Send the thank you email to the person who signed up.
   *
   * @param int $contactID
   * @param array $data
   *    The validated input data.
   */
  public function sendThankYouEmail(int $contactID, array $data, int $thanksMsgTplID) {

    $from = civicrm_api3('OptionValue', 'getvalue', [ 'return' => "label", 'option_group_id' => "from_email_address", 'is_default' => 1]);

    // We use the email send in the data, as that's what they'd expect.
    $params = [
      'id'             => $thanksMsgTplID,
      'from'           => $from,
      'to_email'       => $data['email'],
      // 'bcc'            => "forums@artfulrobot.uk",
      'contact_id'     => $contactID,
      'disable_smarty' => 1,
      /*
      'template_params' =>
      [ 'foo' => 'hello',
      // {$foo} in templates 'bar' => '123',
      // {$bar} in templates ],
      */
      ];

    try {
      civicrm_api3('MessageTemplate', 'send', $params);
    }
    catch (\Exception $e) {
      // Log silently.
      Civi::log()->error("Failed to send MessageTemplate with params: " . json_encode($params, JSON_PRETTY_PRINT) . " Caught " . get_class($e) . ": " . $e->getMessage());
    }
  }
  /**
   */
  public function syncImages() {
    if ($this->getCaseStatus() === 'grpet_Pending') {
      $this->deactivateImages();
    }
    else {
      // Case is public
      $this->activateImages();
    }
  }
  /**
   * Returns absolute file path or url to an image.
   *
   * A URL is only returned if the file exists, however the path is always returned.
   *
   * If $activityID is 0 then a temp file name is returned.
   */
  public function getFile(string $pathOrUrl, ?int $activityID=NULL) :?string {
    $petitionHash = substr(sha1(CIVICRM_SITE_KEY . $this->case['id']), 0, 8);

    if ($activityID === 0) {
      if ($pathOrUrl !== 'path') {
        throw new \RuntimeException("requires 'path' if temp file requested");
      }
      $unique = substr(sha1(CIVICRM_SITE_KEY . 'grpe tempfile' ), 0, 8);
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
    if (!in_array(($attachment['mime_type'] ?? ''), ['image/jpeg'])) {
      // File is not of correct type.
      throw new \RuntimeException("Attachment $attachmentID is not image/jpeg");
    }
    if (!preg_match('/\.jpe?g$/', $attachment['name'] ?? '')) {
      // File is not of correct extension.
      throw new \RuntimeException("Attachment $attachmentID does not have jpeg/jpg extension.");
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
    if($imgProperties[2] !== IMAGETYPE_JPEG) {
      throw new \RuntimeException("Attachment $attachmentID file $src is not a jpeg file according to GD");
    }
    $srcImage = imagecreatefromjpeg($src);
    // Calculate new size.
    // We need images that are 1000px wide.
    $newW = 1000;
    $ratio = 16/9;

    $maxH = (int) ($newW / $ratio);
    $newH = (int) ($imgProperties[1] * $newW / $imgProperties[0]);
    $offsetY = 0;
    $copyW = $imgProperties[0]; // copy full width
    $copyH = $imgProperties[1]; // copy full height
    if ($newH > $maxH) {
      // Image is taller than 16:9 ratio.
      // We will take a crop from the middle.
      $offsetY = (int) (($imgProperties[1] - $imgProperties[0]/$ratio)/2);
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
    // Save file.
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

    // Get first attachment for this activity.
    $attachment = civicrm_api3('Attachment', 'get', [
      'entity_table' => 'civicrm_activity',
      'entity_id' => $openCase['id'],
      'options' => ['limit' => 1, 'sort' => 'id'],
    ]);

    $filePath = $this->getFile('path');

    if ($attachment['count'] == 0) {
      // No main image.
      if (file_exists($filePath)) {
        unlink($filePath);
        Civi::log()->info("GrassrootsPetition: Deleted file '$filePath' since Case {$this->case['id']} no longer has an image file attached.");
      }
      return ['url' => NULL, 'alt' => NULL];
    }

    if (!file_exists($filePath)) {
      // File does not exist.
      $tempFile = $this->createPublicImage($attachment['values'][$attachment['id']]);
      if ($tempFile) {
        rename($tempFile, $filePath);
        Civi::log()->info("GrassrootsPetition: Created file '$filePath' for Case {$this->case['id']}");
      }
    }

    return ['url' => $this->getFile('url'), 'alt' => $attachment['description']];
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
        'case_id' => $this->case['id'],
        'activity_type_id' => static::$activityTypesByName['Grassroots Petition created']['value'],
        'return' => ['id', 'status_id']
      ]);
      if (empty($openCase['id'])) {
        // This is an error!
        throw new \RuntimeException("Case {$this->case['id']} has no Grassroots Petition created activity.");
      }
      $this->createdActivity = $openCase;
    }
    return $this->createdActivity;
  }

  /**
   * Assert that the case is loaded; used by public getters.
   */
  protected function mustBeLoaded() {
    if (empty($this->case)) {
      throw new \RuntimeException("CaseWrapper: no case loaded.");
    }
  }
}
