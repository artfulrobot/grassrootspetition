<?php

use CRM_Grassrootspetition_ExtensionUtil as E;

/*
 * Run this with: cv scr removeAllData.php
 */
if (php_sapi_name() !== 'cli') {
  http_response_code(404);
  exit;
}

$u = new CRM_Grassrootspetition_Upgrader(E::SHORT_NAME, E::path());
$u->ensureDataStructuresExist();
