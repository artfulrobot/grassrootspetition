<?php
namespace Civi\Api4;

use Civi\Api4\GrassrootsPetition\Action\CampaignGetAction;

/**
 * GrassrootsPetitionCampaign entity.
 *
 * Provided by the Grassroots Petition extension.
 *
 * @package Civi\Api4
 */
class GrassrootsPetitionCampaign extends Generic\DAOEntity {

  /**
   * This is the factory method for the get action.
   *
   * @param bool $checkPermissions
   * @return DAOGetAction
   */
  public static function get($checkPermissions = TRUE) {
    return (new CampaignGetAction(static::class, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

}
