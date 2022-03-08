<?php

use CRM_Grassrootspetition_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Grassrootspetition_Form_Settings extends CRM_Admin_Form_Setting {

  protected $_settings = [
    'grpet_public_url' => CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME,
    'grpet_public_admin_url' => CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME,
  ];

  public function buildQuickForm() {
    parent::buildQuickForm();
    $this->assign('elementNames', array_keys($this->_settings));
  }

  /*
  public function postProcess() {
    $values = $this->exportValues();
    $options = $this->getColorOptions();
    CRM_Core_Session::setStatus(E::ts('You picked color "%1"', array(
      1 => $options[$values['favorite_color']],
    )));
    parent::postProcess();
  }

  public function getColorOptions() {
    $options = array(
      '' => E::ts('- select -'),
      '#f00' => E::ts('Red'),
      '#0f0' => E::ts('Green'),
      '#00f' => E::ts('Blue'),
      '#f0f' => E::ts('Purple'),
    );
    foreach (array('1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e') as $f) {
      $options["#{$f}{$f}{$f}"] = E::ts('Grey (%1)', array(1 => $f));
    }
    return $options;
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
// public function getRenderableElementNames() {
//   // The _elements list includes some items which should not be
//   // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
//   // items don't have labels.  We'll identify renderable by filtering on
//   // the 'label'.
//   $elementNames = array();
//   foreach ($this->_elements as $element) {
//     /** @var HTML_QuickForm_Element $element */
//     $label = $element->getLabel();
//     if (!empty($label)) {
//       $elementNames[] = $element->getName();
//     }
//   }
//   return $elementNames;
// }

}
