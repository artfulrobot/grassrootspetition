<?php
namespace Civi\Api4;

/**
 *
 * t method static Civi\Api4\Action\GrassrootsPetition\MakeAuthLink makeAuthLink()
 *
 * @package Civi\Api4
 */
class GrassrootsPetition extends \Civi\Api4\Generic\AbstractEntity {

  /**
   * Factory for MakeAuthLink action.
   *
   * @return \Civi\Api4\Action\GrassrootsPetition\MakeAuthLink
   */
  public static function makeAuthLink() :\Civi\Api4\Action\GrassrootsPetition\MakeAuthLink {
    $api = new \Civi\Api4\Action\GrassrootsPetition\MakeAuthLink(__CLASS__, __FUNCTION__);
    return $api;
  }
  /**
   */
  public static function getFields() {
    return (new Generic\BasicGetFieldsAction(__CLASS__, __FUNCTION__, function($getFieldsAction) {
      return [];
    }));
  }

  /**
   * Restrict access.
   */
  public static function permissions() {
    return [
      // To be used for all actions where there isn't a specific permission set
      'default' => ['access all cases and activities'],
      'getAuthLink' => ['access all cases and activities'],
      // for meta actions e.g. getActions, getFields etc
      //'meta' => [<some permission>]
    ];
  }
}
