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


  /**
   * Adds a 'stats' key to each campaign record.
   *
   * The value of the stats key is an array structure like:
   */
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
   * Creates a structure like so:
   * {
   *   <campaignID>: {
   *     total: (int) total across all petitions for this campaign
   *     petitions: {
   *       <caseID>: {
   *         total: (int),
   *         status: petition status option name
   *         updatesToMod: (int),
   *         title: (string)
   *         slug: (string)
   *       },
   *       ...
   *     }
   *   },
   *   ...
   * }
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
    $progressUpdateTypeID = (int) CaseWrapper::$activityTypesByName['Grassroots Petition progress']['value'];
    $updateNeedsModeration = (int) CaseWrapper::$activityStatuses['grpet_pending_moderation'];
    $petitionNeedsModerationStatus = (int) CaseWrapper::$caseStatusesByName['grpet_Pending']['value'];
    $grpetCaseTypeID = (int) CaseWrapper::$caseTypeID;

    $sql = "
      WITH progressToModByCampaign AS (
        SELECT progressCa.case_id, COUNT(*) c
        FROM civicrm_activity progressA
        INNER JOIN civicrm_case_activity progressCa ON progressA.id = progressCa.activity_id
        WHERE progressA.activity_type_id = $progressUpdateTypeID
          AND progressA.status_id = $updateNeedsModeration
        GROUP BY case_id
      ),

      signerCounts AS (
        SELECT ca.case_id, COUNT(*) signatures
        FROM civicrm_activity a

        /* We need the Case ID, which is what the signature belongs to */
        INNER JOIN civicrm_case_activity ca ON a.id = ca.activity_id

        /* Join to the contact table to exclude deleted people */
        INNER JOIN civicrm_activity_contact ac ON ac.activity_id = a.id AND ac.record_type_id = 3 /* target */
        INNER JOIN civicrm_contact c ON ac.contact_id = c.id AND c.is_deleted = 0

        /* We want to count signed petition activitites */
        WHERE a.activity_type_id = $signedActivityTypeID
          AND a.is_deleted = 0
        GROUP BY case_id
      )

      SELECT
        pet.campaign_id campaignID,
        pet.slug,
        cs.id caseID,
        SUM(COALESCE(signerCounts.signatures, 0)) signatures,
        cs.subject petitionTitle,
        cs.status_id caseStatus,
        SUM(cs.status_id = $petitionNeedsModerationStatus) petitionNeedsMod,
        MIN(ccon.contact_id) contactID, /* possibly there are multiple */
        SUM(COALESCE(progressToModByCampaign.c, 0)) updatesToMod

      FROM civicrm_case cs
      INNER JOIN civicrm_case_contact ccon ON ccon.case_id = cs.id
      INNER JOIN civicrm_contact c ON ccon.contact_id = c.id AND c.is_deleted = 0
      INNER JOIN civicrm_grpet_petition pet ON pet.entity_id = cs.id AND pet.campaign_id IN ($campaignIDs)
      LEFT JOIN progressToModByCampaign ON progressToModByCampaign.case_id = cs.id
      LEFT JOIN signerCounts ON signerCounts.case_id = cs.id

      WHERE cs.case_type_id = $grpetCaseTypeID AND cs.is_deleted = 0

      GROUP BY campaignID, caseID WITH ROLLUP;
    ";
    //Civi::log()->info($sql);
    $results = CRM_Core_DAO::executeQuery($sql);
    while ($results->fetch()) {
      $camp = $results->campaignID;
      // There's a record where camp is NULL, which is grand totals, but we ignore that.
      if (!$camp) {
        continue;
      }
      // This row may be a per-petition row, or a campaign total row.
      $petition = $results->caseID ?? 'total';

      // First time we've encountered this campaign? Initialise stats.
      if (!isset($stats[$camp])) {
        // New campaign encountered.
        $stats[$camp] = ['total' => 0, 'petitions' => [], 'updatesToMod' => 0, 'petitionsToMod' => 0];
      }

      if ($petition === 'total') {
        // This row is the total that comes at the end of the list of petitions.
        $stats[$camp]['total'] = $results->signatures;
        $stats[$camp]['petitionsToMod'] = (int) $results->petitionNeedsMod;
        $stats[$camp]['updatesToMod'] = (int) $results->updatesToMod;
        $stats[$camp]['petitionCount'] = count($stats[$camp]['petitions']);
      }
      else {
        $_ = $results->toArray();
        $_['petitionNeedsMod'] = (int) $_['petitionNeedsMod'];
        $_['updatesToMod'] = (int) $_['updatesToMod'];
        // Trnslate the caseStatus to a human friendly label.
        $_['caseStatus'] = CaseWrapper::$caseStatusesByValue[$_['caseStatus']]['label'];
        unset($_['campaignID']);
        unset($_['caseID']);
        $_['caseID'] = $petition;
        $stats[$camp]['petitions'][$petition] = $_;
      }
    }

    // Clean up the petitions arrays as objects awkward in angular.
    unset($camp);
    foreach ($stats as &$camp) {
      $camp['petitions'] = array_values($camp['petitions']);
    }
    unset($camp);

    return $stats;
  }
}

