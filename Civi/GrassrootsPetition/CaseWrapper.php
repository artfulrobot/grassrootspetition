<?php
namespace Civi\GrassrootsPetition;

use Civi\Inlay\GrassrootsPetition;
use CRM_Core_DAO;

/**
 * Various sugar and convenience functions wrapping a Case of type GrassrootsPetition
 */
class CaseWrapper {

  /** @var array holds the api3 case.get output */
  public $case;

  /** @var array Cache of the civicrm_grpet_campaign row data */
  public $campaign;

  /** @var Array */
  public static $activityTypeIDs;

  /**
   * Instantiate an object from the slug
   *
   * @return CaseWrapper
   */
  public static function fromSlug(string $slug) {
    $inlay = new GrassrootsPetition();
    $params = [ $inlay->getCustomFields('grpet_slug') => $slug, 'sequential' => 1 ];
    $cases = civicrm_api3('Case', 'get', $params);
    if ($cases['count'] == 1) {
      $case = new static();
      return $case->loadFromArray($cases['values'][0]);
    }
    return NULL;
  }

  public function __construct() {
    if (!isset(static::$activityTypeIDs)) {
      // Look these up once now.
      static::$activityTypeIDs = Civi\Api4\OptionValue::get(FALSE)
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
      'location'       => $this->getCustomData('grpet_location'),
      'slug'           => $this->getCustomData('grpet_slug'),
      'targetCount'    => $this->getCustomData('grpet_target_count'),
      'targetName'     => $this->getCustomData('grpet_target_name'),
      'tweet'          => $this->getCustomData('grpet_tweet_text'),
      'petitionHTML'   => $this->case['details'] ?? '',
      'campaign'       => $this->getCampaignPublicName(),
      'image'          => $this->getPetitionImageURL(), /* @todo */
      'updates'        => $this->getPetitionUpdates(), /* @todo */
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
    $this->getCampaign()['label'];
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
      $this->campaign = \Civi\Api4\GrassrootsPetitionCampaign::get(FALSE)
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
   * @todo
   */
  public function getPetitionUpdates() {

  }
  /**
   * Returns a count of the signatures on a particular case.
   */
  public function getPetitionSigsCount() :int {
    $this->mustBeLoaded();
    // Count the 'Grassroots Petition signed' petitions.
    $signedActivityTypeID = (int) static::$activityTypeIDs['Grassroots Petition signed']['value'];
    if (!$signedActivityTypeID) {
      throw new \RuntimeException("Failed to identify Grassroots Petition signed activity type. Check installation.");
    }

    $caseID = (int) $this->case['id'];

    // Count signed activities on this case from live contacts (i.e. exclude deleted contacts).
    $sql = "
      SELECT COUNT(*)
      FROM civicrm_activity a
      INNER JOIN civicrm_case_activity ca ON a.id = ca.activity_id AND ca.case_id = $caseID
      INNER JOIN civicrm_activity_contact ac ON ac.activity_id = a.id AND ac.record_type_id = 3 /* target */
      INNER JOIN civicrm_contact c ON ac.contact_id = c.id AND c.is_deleted = 0
      WHERE a.activity_type_id = $signedActivityTypeID
    ";
    $count = (int) CRM_Core_DAO::singleValueQuery($sql);

    return $count;
  }
  /**
   */
  protected function mustBeLoaded() {
    if (empty(static::$case)) {
      throw new \RuntimeException("CaseWrapper: no case loaded.");
    }
  }
}
