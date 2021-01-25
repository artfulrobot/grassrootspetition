<?php
namespace Civi\Api4\GrassrootsPetition\Action;

use Civi\Api4\Generic\DAOGetAction;
use Civi\Api4\Generic\Result;
use Civi\GrassrootsPetition\CaseWrapper;
use CRM_Core_DAO;
use Civi;

class CampaignGetAction extends DAOGetAction {

  /**
   * Should we load statistics for the results?
   *
   * @var bool
   */
  protected $withStats = FALSE;

  public function _run(Result $result) {

    // Do the same as normal...
    parent::_run($result);

    // Now did we need stats?
    if (TRUE || $this->withStats) {
      // Gather campaign IDs.
      $campaignIDs = $result->column('id');

      // Fetch stats for each campaign.
      $stats = $this->fetchStats($campaignIDs);
      foreach ($result as &$campaign) {
        $campaign['stats'] = $stats[$campaign['id']] ?? ['total' => 0, 'petitions' => []];
      }
    }

  }

  /**
   *
   */
  protected function fetchStats(array $campaignIDs) :array {
    $stats = [];
    if (!$campaignIDs) {
      return $stats;
    }

    // Create SQL to limit to our campaign IDs.
    $campaignIDs = implode(', ', array_map(function ($id) { return (int) $id; }, $campaignIDs));

    CaseWrapper::init();
    $signedActivityTypeID = (int) CaseWrapper::$activityTypesByName['Grassroots Petition signed']['value'];

    // Count signed activities on this case from live contacts (i.e. exclude deleted contacts).
    $sql = "
      SELECT pet.campaign_id campaignID,  ca.case_id caseID, COUNT(*) signatures
      FROM civicrm_activity a
      INNER JOIN civicrm_case_activity ca ON a.id = ca.activity_id
      INNER JOIN civicrm_activity_contact ac ON ac.activity_id = a.id AND ac.record_type_id = 3 /* target */
      INNER JOIN civicrm_contact c ON ac.contact_id = c.id AND c.is_deleted = 0
      INNER JOIN civicrm_grpet_petition pet ON pet.entity_id = ca.case_id AND pet.campaign_id IN ($campaignIDs)
      WHERE a.activity_type_id = $signedActivityTypeID
        AND a.is_deleted = 0
      GROUP BY campaignID, caseID WITH ROLLUP;
    ";
    Civi::log()->info($sql);
    $results = CRM_Core_DAO::executeQuery($sql);
    while ($results->fetch()) {
      $camp = $results->campaignID;
      if ($camp) {
        if (!isset($stats[$camp])) {
          $stats[$camp] = ['total' => 0, 'petitions' => []];
        }
        $petition = $results->caseID ?? 'total';
        if ($petition === 'total') {
          $stats[$camp]['total'] = $results->signatures;
        }
        else {
          $stats[$camp]['petitions'][$petition] = $results->signatures;
        }
      }
    }

    return $stats;
  }
}

