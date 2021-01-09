<?php
namespace Civi\GrassrootsPetition;

use Civi\Inlay\GrassrootsPetition;

/**
 * Various sugar and convenience functions wrapping a Case of type GrassrootsPetition
 */
class CaseWrapper {

  /** @var array holds the api3 case.get output */
  public $case;

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

  /**
   *
   * @return CaseWrapper
   */
  public function loadFromArray($data) {
    $this->case = $data;
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
      'campaign'       => $this->getCampaignName(), /* @todo */
      'image'          => $this->getPetitionImageURL(), /* @todo */
      'updates'        => $this->getPetitionUpdates(), /* @todo */
      'signatureCount' => $this->getPetitionSigsCount(), /* @todo */
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
   * @todo
   */
  public function getCampaignName() {

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
   * @todo
   */
  public function getPetitionSigsCount() {

  }
}
