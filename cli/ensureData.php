<?php

use CRM_Grassrootspetition_ExtensionUtil as E;
use Civi\GrassrootsPetition\CaseWrapper;

/*
 * Run this with: cv scr removeAllData.php
 */
if (php_sapi_name() !== 'cli') {
  http_response_code(404);
  exit;
}

$u = new CRM_Grassrootspetition_Upgrader(E::SHORT_NAME, E::path());
//$u->ensureDataStructuresExist();

// Create demo campaign
$campaignID = Civi\Api4\GrassrootsPetitionCampaign::get(FALSE)
  ->addWhere('name', '=', 'Fossil free: divestment')
  ->execute()->first()['id'] ?? 0;

if (!$campaignID) {
  $campaignID = Civi\Api4\GrassrootsPetitionCampaign::create(FALSE)
    ->addValue('name', 'Fossil free: divestment')
    ->addValue('label', 'Fossil free: divestment')
    ->addValue('template_what', '<p>We the undersigned ...</p>')
    ->addValue('template_why', '<p>This is terrible. We should do something.</p>')
    ->execute()->first()['id'];
  // Create demo campaign
  print "Created campaign $campaignID\n";
}
else{
  print "Got campaign $campaignID\n";
}


// Create demo petition, unles already there.
$new = CaseWrapper::fromSlug('foo');
if (!$new) {
  $contactID = 8685;
  $new = CaseWrapper::createNew(
    $contactID, 'Demo campaign', 'Fossil free: divestment', 'Somewhere', 'The power holder', 'foo');

  print "Created case " . $new->getID() . " " . $new->getCustomData('grpet_slug') . "\n";

}
else {
  print "Found case " . $new->getID() . " " . $new->getCustomData('grpet_slug') . "\n";
}
$new->setStatus('Open');
