<?php
namespace Civi\Api4\Action\GrassrootsPetition;

use Civi;
use Civi\Api4\Generic\Result;
use Civi\GrassrootsPetition\CaseWrapper;
use Civi\GrassrootsPetition\Auth;

/**
 * Generates a session auth record for staff admins to be able to impersonate
 * the petition owner.
 *
 */
class MakeAuthLink extends \Civi\Api4\Generic\AbstractAction {

  /**
   * Petition (Case) ID
   *
   * @var int
   */
  protected $id;

  /**
   */
  public function _run(Result $result) {
    $wrapper = CaseWrapper::fromID($this->id);
    $contactID = reset($wrapper->case['contact_id']); // there's also client_id which seems to be the same thing.
    // Create an auth link that will last 1 hour.
    $result['link'] = Civi::settings()->get('grpet_public_admin_url')
      . Auth::createAuthRecord($contactID, 60*60, 'S');
  }
}
