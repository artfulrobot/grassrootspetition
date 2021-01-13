<?php

namespace Civi\Inlay;

use Civi\Inlay\Type as InlayType;
use Civi\Inlay\ApiRequest;
use Civi\Inlay\ApiException;
use Civi\GrassrootsPetition\CaseWrapper;
use Civi;
use Civi\Api4\Inlay;
use CRM_Grassrootspetition_ExtensionUtil as E;

class GrassrootsPetition extends InlayType {

  public static $typeName = 'Grassroots Petition';
  public static $customFieldsMap;
  /**
   * Cache so that when processing a set of queued signups we don't have to
   * load the Inlay instance for each time.
   *
   * Keyed by instance ID.
   *
   * Nb. it is expected/normal for there to be only one instance of this Inlay
   * on a site, since each instance can handle all petitions.
   */
  public static $instanceCache = [];

  public static $defaultConfig = [
  ];

  /**
   * Note: because of the way CRM.url works, you MUST put a ? before the #
   *
   * @var string
   */
  public static $editURLTemplate = 'civicrm/a?#/inlays/grassrootspetition/{id}';

  /**
   * @return CRM_Queue_Service
   */
  public static function getQueueService() {
    return CRM_Queue_Service::singleton()->create([
      'type'  => 'Sql',
      'name'  => 'inlay-grassrootspetition',
      'reset' => FALSE, // We do NOT want to delete an existing queue!
    ]);
  }
  /**
   * Process a queued submission.
   *
   * This is the callback for the queue runner.
   *
   * Nb. the data has already been validated.
   *
   * @param mixed?
   * @param array
   *
   * @return bool TRUE if it went ok, FALSE will prevent further processing of the queue.
   */
  public static function processQueueItem($queueTaskContext, $data) {

    // Get instance ID.
    $id = (int) $data['inlayID'];

    // Get the Inlay Object from database if we don't have it cached.
    if (($id > 0) && !isset(static::$instanceCache[$id])) {
      $inlayData = \Civi\Api4\Inlay::get(FALSE)
        ->setCheckPermissions(FALSE)
        ->addWhere('id', '=', (int) $data['inlayID'])
        ->execute()->first();
      $inlay = new static();
      $inlay->loadFromArray($inlayData);
      // Store on cache.
      static::$instanceCache[$id] = $inlay;
    }

    // Error if we couldn't find it.
    if (empty(static::$instanceCache[$id])) {
      throw new \RuntimeException("Invalid Inlay/GrassrootsPetition queue item, failed to load instance.");
    }

    // Finally, use it to process the data.
    $error = static::$instanceCache[$id]->processSubmission($data);
    if ($error) {
      // ?? How to handle errors.
      // @todo
    }

    // Move on to next item in queue.
    return TRUE;
  }
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

    $routes = [
      'GET' => [
        'publicData' => 'processGetPublicDataRequest',
      ],
      'POST' => [
        'submitSignature' => 'processSubmitSignatureRequest',
      ]
    ];
    $method = $routes[$request->getMethod()][$request->getBody()['need'] ?? ''] ?? NULL;
    if (empty($method)) {
      throw new ApiException(400, ['publicError' => 'Invalid request. (Routing error)'],
        "GrassrootsPetition API called with invalid 'need'");
    }

    return $this->$method($request);
  }
  /**
   * Finds the petition and returns its definition, counts etc.
   *
   * @return array
   */
  public function processGetPublicDataRequest(ApiRequest $request) {
    $case = $this->getCaseWrapperFromRequest($request);
    // Extract what we need from the case.
    return ['publicData' => $case->getPublicData()];
  }
  /**
   * Handle signature submission.
   *
   * @return array
   */
  public function processSubmitSignatureRequest(ApiRequest $request) {
    // This will throw exception if the case is not found.
    $case = $this->getCaseWrapperFromRequest($request);

    // Extract valid data
    $data = $this->cleanupInput($request->getBody());

    if (empty($data['token'])) {
      // Unsigned request. Issue a token that will be valid in 5s time and lasts 2mins max.
      return ['token' => $this->getCSRFToken(['data' => $data, 'validFrom' => 5, 'validTo' => 120])];
    }

    // Store the Case ID on the data, now we know it's valid.
    // This will speed up the processing (from queue).
    $data['case_id'] = $case->getID();

    //if ($this->config['useQueue'])
    if (FALSE) { // todo
      // Defer processing the data to a queue. This speeds things up for the user
      // and avoids database deadlocks.
      $queue = static::getQueueService();

      // We have context that is not stored in $data, namely which Inlay Instance we are.
      // Store that now.
      $data['inlayID'] = $this->getID();

      $queue->createItem(new CRM_Queue_Task(
        ['Civi\\Inlay\\GrassrootsPetition', 'processQueueItem'], // callback
        [$data], // arguments
        "Grassroots Petition signature" // title
      ));

      return ['success' => 1];
    }

    // Immediate processing.
    $errorString = $this->processSubmission($data);
    return $errorString ? ['error' => $errorString] : ['success' => 1];
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
    /** @var Array errors in this array, it will later be converted to a string. */
    $errors = [];
    /** @var Array Collect validated data in this array */
    $valid = [];

    // Check we have what we need.
    foreach (['first_name', 'last_name', 'email'] as $field) {
      $val = trim($data[$field] ?? '');
      if (empty($val)) {
        $errors[] = str_replace('_', ' ', $field) . " required.";
      }
      else {
        if ($field === 'email' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
          $errors[] = "invalid email address";
        }
        else {
          $valid[$field] = $val;
        }
      }
    }

    // Check we've not been fed a web address as a name
    // (used by spammers who rely on "Dear {firstname}")
    // We check the concat in case they rely on first-last or last-first to form a url.
    // v1
    if (preg_match('#(www|http|[%@:/?$])#i', "$valid[first_name]$valid[last_name]$valid[first_name]")) {
      $errors[] = "Invalid name";
    }

    // Optin.
    if (preg_match('/^(yes|no)$/', $data['optin'] ?? '')) {
      $valid['optin'] = $data['optin'];
    }
    else {
      $errors[] = "Please confirm consent for future communications.";
    }

    // Location should be the URL of the page.
    $valid['location'] = $data['location'] ?? '';

    // Phone is optional
    $valid['phone'] = '';
    if (!empty($data['phone'])) {
      // Require at least 11 numbers.
      if (!preg_match('/[0-9]{11,}/', preg_replace('/[^0-9]+/', '', $data['phone']))) {
        $errors[] = "Your phone number does not look valid. (Nb. providing a phone is optional.)";
      }
      else {
        // Strip out everything that looks phoney. I mean non-phoney. I mean...
        $valid['phone'] = preg_replace('/[^0-9]+/', '', $data['phone']);
      }
    }

    if ($errors) {
      throw new \Civi\Inlay\ApiException(400, ['error' => implode(', ', $errors)]);
    }

    // Data is valid.
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
        throw new \Civi\Inlay\ApiException(400,
          ['error' => "Mysterious problem, sorry! Code " . substr($e->getMessage(), 0, 3)]);
      }

      // Validation that is more expensive, and for fields where invalid data
      // would likely represent misuse of the form is done now - after the
      // token check, to avoid wasting server resources on spammers trying to
      // randomly post to the endpoint.

      /*
      if ($this->config['phoneAsk'] && !empty($data['phone'])) {
        // Check the phone.
        $valid['phone'] = preg_replace('/[^0-9+]/', '', $data['phone']);
      }
       */
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

  /**
   * Maps field custom field names to API3 names like custom_N
   *
   * If $field given, return that field's API3 name, otherwise return them all as an array.
   *
   * - grpet_target_name
   * - grpet_target_count
   * - grpet_tweet_text
   * - grpet_location
   * - grpet_campaign
   * - grpet_slug
   * - grpet_sig_public
   * - grpet_sig_shared
   *
   * @param NULL|string
   * @return array|string
   */
  public function getCustomFields($field = NULL) {
    if (!isset(static::$customFieldsMap)) {
      // Look up the custom field IDs we need.
      $customFields = \Civi\Api4\CustomField::get(FALSE)
        ->setCheckPermissions(FALSE)
        ->addSelect('id', 'name')
        ->addWhere('custom_group_id:name', 'IN', ['grpet_signature', 'grpet_petition'])
        ->execute()
        ->indexBy('name')->column('id');
      static::$customFieldsMap = [];
      foreach ($customFields as $name => $id) {
        static::$customFieldsMap[$name] = "custom_$id";
      }
    }

    if ($field === NULL) {
      return static::$customFieldsMap;
    }
    if (isset(static::$customFieldsMap[$field])) {
      return static::$customFieldsMap[$field];
    }
    throw new \RuntimeException("GrassrootsPetition::getCustomFields called for unknown field '$field'");
  }
  /**
   * Process a submission.
   *
   * This is where the bulk of the work is done.
   *
   * @var array $data
   */
  public function processSubmission($data) {

    $case = CaseWrapper::fromID($data['case_id']);
    if (!$case) {
      throw new \Civi\Inlay\ApiException(400, ['error' => 'Petition not found'],
        "Failed to load case with ID " . json_encode($data['case_id']));
    }

    // Find Contact with XCM.
    // @todo source?
    $params = array_intersect_key($data, array_flip(
      ['first_name', 'last_name', 'email']
    )) + ['contact_type' => 'Individual'];
    // Add phone in, if given.
    if ($data['phone']) {
      $params['phone'] = $data['phone'];
    }

    $contactID = (int) civicrm_api3('Contact', 'getorcreate', $params)['id'] ?? 0;
    if (!$contactID) {
      Civi::log()->error('Failed to getorcreate contact with params: ' . json_encode($params));
      throw new \Civi\Inlay\ApiException(500, ['error' => 'Server error: XCM1']);
    }

    // Create a signature activity, if not already signed.
    if ($case->getPetitionSigsCount($contactID) === 0) {
      $activityID = $case->addSignedPetitionActivity($contactID, $data);
    }

    // They must agree the GRPR stuff when submitting the form.
    if (class_exists('CRM_Gdpr_SLA_Utils')) {
      \CRM_Gdpr_SLA_Utils::recordSLAAcceptance($contactID);
    }

    if ($data['optin'] === 'yes') {
      $case->recordConsent($contactID, $data);
    }

    // xxx
    // Handle optin.
    /*
    if (!empty($this->config['mailingGroup'])) {
      $optinMode = $this->config['optinMode'];

      // If there was no optin (e.g. signup form)
      // or if the user actively checked/selected yes, then sign up.
      if ($optinMode === 'none'
        || ($data['optin'] ?? 'no') === 'yes') {
        // Add contact to the group.
        $this->addContactToGroup($contactID);
      }
    }
     */

    // Thank you.
    $case->sendThankYouEmail($contactID, $data);

    // No error
    return '';
  }


  /**
   * Has the given contact signed this petition already?
   *
   * @var int $contactID
   *
   * @return bool
   */
  public function contactAlreadySigned($contactID) {

    $subject = $this->getName();
    $activityTypeID = $this->getActivityTypeID();

    $found = (bool) \CRM_Core_DAO::singleValueQuery("
        SELECT a.id
        FROM civicrm_activity a
        INNER JOIN civicrm_activity_contact ac
        ON a.id = ac.activity_id
            AND ac.record_type_id = 3 /*target*/
            AND ac.contact_id = %1
        WHERE a.activity_type_id = %2 AND a.subject = %3
        LIMIT 1
      ", [
        1 => [$contactID, 'Integer'],
        2 => [$activityTypeID, 'Integer'],
        3 => [$subject, 'String'],
      ]);

    return $found;
  }
  /**
   */
  public function addSignedPetitionActivity(int $contactID, array $data) {

    $activityCreateParams = [
      'activity_type_id'     => $this->getActivityTypeID(),
      'target_id'            => $contactID,
      'subject'              => $this->getName(),
      'status_id'            => 'Completed',
      'source_contact_id'    => $contactID,
      // 'source_contact_id' => \CRM_Core_BAO_Domain::getDomain()->contact_id,
      // 'details'           => $details,
    ];
    $result = civicrm_api3('Activity', 'create', $activityCreateParams);

    return $result;
  }
  /**
   * DRY method.
   */
  protected function getCaseWrapperFromRequest(ApiRequest $request) :CaseWrapper {
    $rawInput = $request->getBody();
    $slug = $rawInput['petitionSlug'] ?? '';

    // We assume nginx has done some caching so if we're called here we need to do all the work again.

    // The slug is stored as custom data on the case.
    $case = CaseWrapper::fromSlug($slug);
    if (!$case) {
      throw new ApiException(400, ['publicError' => 'Petition not found.']);
    }

    switch ($case->getCaseStatus()) {
    case 'grpet_Pending':
      throw new ApiException(400, ['publicError' => 'Petition not published yet.']);

    }
    // Allowing: gpet_Dead|gpet_Won|Open

    // @todo Check is public - but not if privileged call?

    // Extract what we need from the case.

    return $case;
  }
}
