<?php
namespace Civi\GrassrootsPetition;

use Civi\Inlay\GrassrootsPetition;
use CRM_Core_DAO;
use Civi\Api4\OptionValue;
use Civi\Api4\GrassrootsPetitionCampaign;

/**
 * Various sugar and convenience functions wrapping a Case of type GrassrootsPetition
 */
class CaseWrapper {

  /** @var array holds the api3 case.get output */
  public $case;

  /** @var array Cache of the civicrm_grpet_campaign row data */
  public $campaign;

  /** @var Array */
  public static $activityTypesByName;

  /** @var array Status name to option value */
  public static $activityStatuses;
  /** @var array Status name to option value */
  public static $caseStatuses;
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

  public function __construct() {
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
   *
   * @return CaseWrapper
   */
  public function loadFromArray($data) {
    $this->case = $data;
    // This gets looked up later, if needed.
    $this->campaign = NULL;
    return $this;
  }

  /**
   * Return the data needed to present the petition.
   *
   * @return array
   */
  public function getPublicData() {
    $public = [
      'status'         => $this->getCaseStatus(),
      'location'       => $this->getCustomData('grpet_location'),
      'slug'           => $this->getCustomData('grpet_slug'),
      'targetCount'    => $this->getCustomData('grpet_target_count'),
      'targetName'     => $this->getCustomData('grpet_target_name'),
      'tweet'          => $this->getCustomData('grpet_tweet_text'),
      'petitionTitle'  => $this->case['subject'] ?? '',
      'petitionHTML'   => $this->case['details'] ?? '',
      'campaign'       => $this->getCampaignPublicName(),
      'image'          => $this->getPetitionImageURL(), /* @todo */
      'updates'        => $this->getPetitionUpdates(),
      'signatureCount' => $this->getPetitionSigsCount(),
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
   * @todo
   */
  public function getPetitionImageURL() {

  }
  /**
   * Updates are activities of the 'Grassroots Petition progress' type
   */
  public function getPetitionUpdates() {
    $this->mustBeLoaded();
    $caseID = (int) $this->case['id'];

    $updateActivityTypeID = (int) $this->activityTypes['Grassroots Petition update']['id'];
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
            $andIsSelectedContact
    ";
    $count = (int) CRM_Core_DAO::singleValueQuery($sql);

    // $count+= rand(23,76); // xxx todo
    return $count;
  }
  /**
   * Return the name of the case status from its value.
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
   * Add a signed petition activity to the case for the given contact.
   *
   * @todo location/source
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
      // 'details'           => $details,
      // 'location' todo
    ];
    $result = civicrm_api3('Activity', 'create', $activityCreateParams);

    return (int) $result['id'];
  }
  /**
   */
  public function sendThankYouEmail(int $contactID, array $data) {
    // todo
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
