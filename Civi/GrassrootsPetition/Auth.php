<?php
namespace Civi\GrassrootsPetition;

use CRM_Core_DAO;

class Auth {
  /**
   * Create stored random hash.
   *
   * @param int $contactID
   * @param int $ttl Time to live in seconds. e.g. 60 means it will only last 60 seconds.
   */
  public static function createAuthRecord(int $contactID, int $ttl) {

    $length = 16;
    $hash = substr(hash_hmac('sha256', random_bytes($length), CIVICRM_SITE_KEY), 0, $length);
    $sql = "INSERT INTO civicrm_grpet_auth (id, contact_id, validTo) VALUES (%1, %2, %3)";
    CRM_Core_DAO::executeQuery($sql, [
      1 => [$hash, 'String'],
      2 => [$contactID, 'Positive'],
      3 => [date('Y-m-d H:i:s', time() + $ttl), 'String'],
    ]);

    return $hash;
  }
  /**
   * Return the contactID identified by the given hash, or NULL.
   */
  public static function checkAuthRecord($hash) :?int {

    if (!preg_match('/^[0-9a-z]{16}$/', $hash)) {
      // Invalid syntax, don't bother with db lookup.
      return NULL;
    }
    // First purge expired tokens.
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_grpet_auth WHERE validTo < CURRENT_TIMESTAMP;");

    // Now check.
    $sql = "SELECT * FROM civicrm_grpet_auth WHERE id = %1";
    $dao = CRM_Core_DAO::executeQuery($sql, [ 1 => [$hash, 'String'] ]);
    if ($dao->fetch()) {
      return $dao->contact_id;
    }
    return NULL;
  }
}
