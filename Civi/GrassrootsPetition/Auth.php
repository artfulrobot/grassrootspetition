<?php
namespace Civi\GrassrootsPetition;

use CRM_Core_DAO;

class Auth {
  /**
   * Create stored random hash.
   *
   * @param int $contactID
   * @param int $ttl Time to live in seconds. e.g. 60 means it will only last 60 seconds.
   * @param string $type 'T' for temporary or 'S' for session.
   */
  public static function createAuthRecord(int $contactID, int $ttl, string $type) {

    $length = 16;
    $hash = $type . substr(hash_hmac('sha256', random_bytes($length), CIVICRM_SITE_KEY), 0, $length);
    $sql = "INSERT INTO civicrm_grpet_auth (id, contact_id, validTo) VALUES (%1, %2, %3)";
    CRM_Core_DAO::executeQuery($sql, [
      1 => [$hash, 'String'],
      2 => [$contactID, 'Positive'],
      3 => [date('Y-m-d H:i:s', time() + $ttl), 'String'],
    ]);

    return $hash;
  }
  /**
   * Return the contactID identified by the given hash, and possibly a new token.
   */
  public static function checkAuthRecord($hash) :array {

    $return =[ 'contactID' => NULL];
    if (!preg_match('/^[TS][0-9a-z]{16}$/', $hash)) {
      // Invalid syntax, don't bother with db lookup.
      return $return;
    }
    // First purge expired tokens.
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_grpet_auth WHERE validTo < CURRENT_TIMESTAMP;");

    // Now check.
    $sql = "SELECT * FROM civicrm_grpet_auth WHERE id = %1";
    $dao = CRM_Core_DAO::executeQuery($sql, [ 1 => [$hash, 'String'] ]);
    if ($dao->fetch()) {
      $return['contactID'] = (int) $dao->contact_id;

      // If this is a temporary token, replace it with a session one.
      if (substr($hash, 0, 1) === 'T') {
        if ($dao->upgradedTo) {
          // There's already a record. Look this up.
          $return['token'] = $dao->upgradedTo;
        }
        else {
          // Create a new session token.
          $return['token'] = static::createAuthRecord((int) $dao->contactID, 60*60*24, 'S');
          // Store it in the upgradedTo field
          CRM_Core_DAO::executeQuery("UPDATE civicrm_grpet_auth SET upgradedTo = %1 WHERE id = %2", [
            1 => [$return['token'], 'String'],
            2 => [$hash, 'String']
          ]);
        }
      }
    }
    return $return;
  }
}
