<?php

require_once 'grassrootspetition.civix.php';
// phpcs:disable
use CRM_Grassrootspetition_ExtensionUtil as E;
// phpcs:enable

/**
 * Implements hook_civicrm_container().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_container/
 */
function grassrootspetition_civicrm_container($container) {
  // https://docs.civicrm.org/dev/en/latest/hooks/usage/symfony/
  //Civi::dispatcher()
  $container->findDefinition('dispatcher')
    ->addMethodCall('addListener', ['hook_inlay_registerType', [Civi\Inlay\GrassrootsPetition::class, 'register']]);
}
/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function grassrootspetition_civicrm_config(&$config) {
  _grassrootspetition_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_xmlMenu
 */
function grassrootspetition_civicrm_xmlMenu(&$files) {
  _grassrootspetition_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function grassrootspetition_civicrm_install() {
  _grassrootspetition_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function grassrootspetition_civicrm_postInstall() {
  _grassrootspetition_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function grassrootspetition_civicrm_uninstall() {
  _grassrootspetition_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function grassrootspetition_civicrm_enable() {
  _grassrootspetition_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function grassrootspetition_civicrm_disable() {
  _grassrootspetition_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 */
function grassrootspetition_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _grassrootspetition_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
 */
function grassrootspetition_civicrm_managed(&$entities) {
  _grassrootspetition_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_caseTypes
 */
function grassrootspetition_civicrm_caseTypes(&$caseTypes) {
  _grassrootspetition_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules
 */
function grassrootspetition_civicrm_angularModules(&$angularModules) {
  _grassrootspetition_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterSettingsFolders
 */
function grassrootspetition_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _grassrootspetition_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function grassrootspetition_civicrm_entityTypes(&$entityTypes) {
  _grassrootspetition_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_themes().
 */
function grassrootspetition_civicrm_themes(&$themes) {
  _grassrootspetition_civix_civicrm_themes($themes);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess
 */
//function grassrootspetition_civicrm_preProcess($formName, &$form) {
//
//}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
function grassrootspetition_civicrm_navigationMenu(&$menu) {
  _grassrootspetition_civix_insert_navigation_menu($menu, 'Cases', [
    'label' => E::ts('Grassroots Petitions'),
    'name' => 'grpet_campaigns_admin',
    'url' => 'civicrm/a#/grassrootspetition/campaigns',
    'permission' => 'access my cases and activities,access all cases and activities',
    'operator' => 'OR',
    'separator' => 0,
  ]);
  _grassrootspetition_civix_navigationMenu($menu);
}
/**
 * Implements hook_civicrm_buildForm
 * https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_buildForm/
 *
 * This converts what would be a <input type=text> to a searchable select2 field for the two email template overrides.
 */
function grassrootspetition_civicrm_buildForm($formName, &$form) {
  \Civi::log()->info($formName);
  if ($formName === 'CRM_Case_Form_CustomData') {
    CRM_Core_Region::instance('form-bottom')->add([
      'jquery' => <<<JAVASCRIPT
          // confirm email
          $('[name="custom_112_3"]').crmEntityRef({
            entity: 'MessageTemplate',
            api: {
              search_field: 'msg_title',
              label_field: 'msg_title',
              description_field: 'msg_subject',
              params: { is_active: 1 },
            },
            create: false
          });
          // thanks email
          $('[name="custom_111_3"]').crmEntityRef({
            entity: 'MessageTemplate',
            api: {
              search_field: 'msg_title',
              label_field: 'msg_title',
              description_field: 'msg_subject',
              params: { is_active: 1 },
            },
            create: false
          });
        JAVASCRIPT
    ]);
  }
}
