<?php

namespace Civi\Inlay;

use Civi\Inlay\Type as InlayType;
use Civi\Inlay\ApiRequest;
use Civi;
use Civi\Api4\Inlay;
use CRM_Grassrootspetition_ExtensionUtil as E;

class GrassrootsPetition extends InlayType {

  public static $typeName = 'Grassroots Petition';

  public static $defaultConfig = [
  ];

  /**
   * Note: because of the way CRM.url works, you MUST put a ? before the #
   *
   * @var string
   */
  public static $editURLTemplate = 'civicrm/a?#/inlays/grassrootspetition/{id}';

  /**
   * Sets the config ensuring it's valid.
   *
   * This implementation simply ensures all the defaults exist, and that no
   * other keys exist, but you could do other things, especially if you need to
   * coerce some old config into a new style.
   *
   * @param array $config
   *
   * @return \Civi\Inlay\Type (this)
   */
  public function setConfig(array $config) {
    $this->config = array_intersect_key($config + static::$defaultConfig, static::$defaultConfig);
  }

  /**
   * Generates data to be served with the Javascript application code bundle.
   *
   * @return array
   */
  public function getInitData() {
    $init = [
      // Name of global Javascript function used to boot this app.
      'init'             => 'inlayGrpetInit',
    ];
    return $init;
  }

  /**
   * Process a request
   *
   * Request data is just key, value pairs from the form data. If it does not
   * have 'token' field then a token is generated and returned. Otherwise the
   * token is checked and processing continues.
   *
   * @param \Civi\Inlay\Request $request
   * @return array
   *
   * @throws \Civi\Inlay\ApiException;
   */
  public function processRequest(ApiRequest $request) {

    $data = $this->cleanupInput($request->getBody());

    if (empty($data['token'])) {
      // Unsigned request. Issue a token that will be valid in 5s time and lasts 2mins max.
      return ['token' => $this->getCSRFToken(['data' => $data, 'validFrom' => 5, 'validTo' => 120])];
    }

    // Hand over to the form processor.
    // @todo process submission

    return [ 'success' => 1 ];
  }

  /**
   * Validate and clean up input data.
   *
   * @todo
   *
   * @param array $data
   *
   * @return array
   */
  public function cleanupInput($data) {
    $errors = [];
    $valid = [];

    // Here I would like to call the form processor, but only as far as
    // validating the inputs, not actually executing it.
    // However, the validation is all coded together with the
    // invokeFormProcessor() execute code, so that can't happen right now.
    //
    // Instead we'll just ensure that the only data we pass on is that which
    // correlates to the inputs of the form processor.
    $fp = civicrm_api3('FormProcessorInstance', 'get', ['sequential' => 1, 'name' => $this->config['formProcessor']])['values'][0] ?? NULL;
    if (!$fp) {
      Civi::log()->error("Inlay error FP1: failed to load form processor for the Inlay called " . $this->getName());
      throw new \Civi\Inlay\ApiException(500, "Sorry, this form has not been configured correctly. Error: FP1");
    }

    foreach ($fp['inputs'] as $_) {
      $inputName = $_['name'];
      if (isset($data[$inputName])) {
        $valid[$inputName] = $data[$inputName];
      }
      elseif ($_['is_required'] == 1) {
        // A required input is not present in the request. This is not going to work.
        // It's probably a configuration error - e.g. didn't add the field to the form.
        Civi::log()->error("Inlay error FP2: Form Processor in put $inputName is required but has not been sent with a request. Has the input been added to the form correctly? Inlay Name: " . $this->getName());
        throw new \Civi\Inlay\ApiException(500, "Sorry, this form has not been configured correctly. Error: FP2");
      }
    }

    // Ok, we don't know if the data is valid, but we do know that $valid now
    // only contains the inputs, and that none of the required inputs are
    // missing.

    if (!empty($data['token'])) {
      // There is a token, check that now.
      try {
        $this->checkCSRFToken($data['token'], $valid);
        $valid['token'] = TRUE;
      }
      catch (\InvalidArgumentException $e) {
        // Token failed. Issue a public friendly message, though this should
        // never be seen by anyone legit.
        Civi::log()->notice("Token error: " . $e->getMessage . "\n" . $e->getTraceAsString());
        watchdog('inlay', $e->getMessage() . "\n" . $e->getTraceAsString, array(), WATCHDOG_ERROR);
        throw new \Civi\Inlay\ApiException(400,
          "Mysterious problem, sorry! Code " . substr($e->getMessage(), 0, 3));
      }
    }

    return $valid;
  }

  /**
   * Returns a URL to a page that lets an admin user configure this Inlay.
   *
   * @return string URL
   */
  public function getAdminURL() {

  }

  /**
   * Get the Javascript app script.
   *
   * This will be bundled with getInitData() and some other helpers into a file
   * that will be sourced by the client website.
   *
   * @return string Content of a Javascript file.
   */
  public function getExternalScript() {

    $x= file_get_contents(E::path('dist/inlaygrpet.js'));
    if (!$x) {
      throw new \Exception(E::path('dist/inlaygrpet.js')  . " not found");
    }
    return file_get_contents(E::path('dist/inlaygrpet.js'));
  }

}
