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
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function grassrootspetition_civicrm_install() {
  _grassrootspetition_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function grassrootspetition_civicrm_enable() {
  _grassrootspetition_civix_civicrm_enable();
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
    'label' => E::ts('Grassroots Petition Campaigns'),
    'name' => 'grpet_campaigns_admin',
    'url' => 'civicrm/a#/grassrootspetition/campaigns',
    'permission' => 'access my cases and activities,access all cases and activities',
    'operator' => 'OR',
    'separator' => 0,
  ]);
  _grassrootspetition_civix_insert_navigation_menu($menu, 'Cases', [
    'label' => E::ts('Grassroots Petition Site Settings'),
    'name' => 'grpet_site_admin',
    'url' => 'civicrm/grassrootspetition/settings',
    'permission' => 'access all cases and activities',
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

    require_once 'CRM/Core/BAO/CustomField.php';
    $confirmMsgTplFieldID = CRM_Core_BAO_CustomField::getCustomFieldID('grpet_confirm_msg_template_id');
    $thanksMsgTplFieldID = CRM_Core_BAO_CustomField::getCustomFieldID('grpet_thanks_msg_template_id');

    CRM_Core_Region::instance('form-bottom')->add([
      'jquery' => <<<JAVASCRIPT
          // confirm email
          $('[name^="custom_{$confirmMsgTplFieldID}_"]').crmEntityRef({
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
          $('[name^="custom_{$thanksMsgTplFieldID}_"]').crmEntityRef({
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
