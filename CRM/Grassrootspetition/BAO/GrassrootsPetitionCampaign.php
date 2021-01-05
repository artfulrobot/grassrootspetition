<?php
use CRM_Grassrootspetition_ExtensionUtil as E;

class CRM_Grassrootspetition_BAO_GrassrootsPetitionCampaign extends CRM_Grassrootspetition_DAO_GrassrootsPetitionCampaign {

  /**
   * Create a new GrassrootsPetitionCampaign based on array-data
   *
   * @param array $params key-value pairs
   * @return CRM_Grassrootspetition_DAO_GrassrootsPetitionCampaign|NULL
   *
  public static function create($params) {
    $className = 'CRM_Grassrootspetition_DAO_GrassrootsPetitionCampaign';
    $entityName = 'GrassrootsPetitionCampaign';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  } */

}
