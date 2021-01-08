<?php
// This file declares an Angular module which can be autoloaded
// in CiviCRM. See also:
// \https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules/n
return [
  'js' => [
    'ang/grassrootspetition.js',
    'ang/grassrootspetition/*.js',
    'ang/grassrootspetition/*/*.js',
  ],
  'css' => [
    'ang/grassrootspetition.css',
  ],
  'partials' => [
    'ang/grassrootspetition',
  ],
  'requires' => [
    'crmUi',
    'crmUtil',
    'ngRoute',
  ],
  'settings' => [],
];
