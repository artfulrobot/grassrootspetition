<?php
/**
 * Remove all data and all data structures related to this extension. For testing/development.
 *
 * Run AFTER uninstalling.
 *
 * Run this with: cv scr removeAllData.php
 */
if (php_sapi_name() !== 'cli') {
  http_response_code(404);
  exit;
}

// Find Case Type
$caseTypeID = civicrm_api3('CaseType', 'get', ['name' => 'grassrootspetition'])['id'] ?? 0;

$answer = '';
while ($answer !== 'a' && $answer !== 'd' && $answer !== 'l') {
  fwrite(STDERR, "(A)ll or (D)ata or (L)ist only? ");
  $answer = strtolower(trim(fgets(STDIN)));
}
$mode = ['a' => 'all', 'd' => 'data', 'l' => 'list'][$answer];
if ($mode === 'data') {
  $mode = 'data';
}
elseif ($mode === 'all') {
  $mode = 'all';
}
elseif ($mode === 'list') {
  if ($caseTypeID) {
    fwrite(STDERR, "Case type exists\n");
    $cases = civicrm_api3('Case', 'get', [
      'return' => ['id'],
      'case_type_id' => $caseTypeID,
      'options' => ['limit' => 0],
    ])['count'] ?? 0;

    $count = 0;
    foreach ($cases as $row) {
      $count += civicrm_api3('Activity', 'getcount', ['case_id' => $row['id']])['count'] ?? 0;
    }
    fwrite(STDERR, count($cases) . " cases of type $caseTypeID with $count activities.\n");
  }
  else {
    fwrite(STDERR, "Case type not defined.\n");
  }

  try {
    $count = CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM civicrm_grpet_campaign;");
    fwrite(STDERR, "$count campaigns");
  }
  catch (\Exception $e) {
    fwrite(STDERR, "Campaigns table not defined\n");
  }
  exit;
}


fwrite(STDERR, "Remove **$mode** from Grassroots petition, you sure? (type yes): ");
$answer = trim(fgets(STDIN));
if ($answer !== 'yes') {
  fwrite(STDERR, "Aborted.");
  exit;
}

function deleteActivities($params) {
  $activities = civicrm_api3('Activity', 'get', $params)['values'] ?? [];
  foreach ($activities as $activity) {
    civicrm_api3('Activity', 'delete', ['id' => $activity['id']]);
  }
}


// Do deleting. ----------------------------------------

// Delete all cases.
if (!empty($caseTypeID)) {
  $cases = civicrm_api3('Case', 'get', [
    'return' => ['id'],
    'case_type_id' => $caseTypeID,
    'options' => ['limit' => 0],
  ])['values'] ?? [];
  foreach ($cases as $row) {
    // First delete activities.
    deleteActivities([ 'case_id' => $row['id'] ]);

    fwrite(STDOUT, "deleting case $row[id]\n");
    civicrm_api3('Case', 'delete', ['id' => $row['id']]);
  }
}
if ($mode === 'data') {
  // Delete the campaings.
  CRM_Core_DAO::executeQuery("DELETE FROM civicrm_grpet_campaign;");
  fwrite(STDOUT, "Deleted campaign definitions.\n");
}

if ($mode === 'all') {
  // Delete Case Statuses
  $caseStatuses = Civi\Api4\OptionValue::delete()
    ->setCheckPermissions(FALSE)
    ->addWhere('option_group_id:name', '=', 'case_status')
    ->addWhere('name', 'LIKE', 'grpet_%')
    ->execute();
  fwrite(STDOUT, "Deleted case statuses "  . json_encode($caseStatuses) . "\n");

  // Delete Activity Statuses
  $caseStatuses = Civi\Api4\OptionValue::delete()
    ->setCheckPermissions(FALSE)
    ->addWhere('option_group_id:name', '=', 'activity_status')
    ->addWhere('name', 'LIKE', 'grpet_%')
    ->execute();
  fwrite(STDOUT, "Deleted activity_status statuses "  . json_encode($caseStatuses) . "\n");

  // Delete Case Type
  if (!empty($caseTypeID)) {
    civicrm_api3('CaseType', 'delete', ['id' => $caseTypeID]);
    fwrite(STDOUT, "Deleted case type $caseTypeID\n");
  }
  else {
    fwrite(STDOUT, "No case type to delete\n");
  }

  // Delete Activity types
  $caseStatuses = Civi\Api4\OptionValue::delete()
    ->setCheckPermissions(FALSE)
    ->addWhere('option_group_id:name', '=', 'activity_type')
    ->addWhere('name', 'IN', [
      'Grassroots Petition created',
      'Grassroots Petition mailing',
      'Grassroots Petition progress',
      'Grassroots Petition signed',
    ])
    ->execute();
  fwrite(STDOUT, "Deleted activity types "  . json_encode($caseStatuses) . "\n");

  $customGroupID = civicrm_api3('CustomGroup', 'get', ['name' => 'grpet_petition'])['id'] ?? NULL;
  $groups = civicrm_api3('CustomGroup', 'get', ['name' => ['IN' => ['grpet_petition', 'grpet_signature']]])['values'] ?? [];
  foreach ($groups as $customGroupID => $details) {
    // delete all fields.
    $fields = civicrm_api3('CustomField', 'get', ['custom_group_id' => $customGroupID])['values'] ?? [];
    foreach ($fields as $field) {
      civicrm_api3('CustomField', 'delete', ['id' => $field['id']]);
      fwrite(STDOUT, "Deleted custom field $field[id] $field[name] on $details[name]\n");
    }
    civicrm_api3('CustomGroup', 'delete', ['id' => $customGroupID]);
    fwrite(STDOUT, "Deleted custom field group $details[name]\n");
  }
}

fwrite(STDOUT, "All done\n");
