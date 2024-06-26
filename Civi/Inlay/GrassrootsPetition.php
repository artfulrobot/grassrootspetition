<?php

namespace Civi\Inlay;

use Civi\Inlay\Type as InlayType;
use Civi\Inlay\ApiRequest;
use Civi\Inlay\ApiException;
use Civi\GrassrootsPetition\CaseWrapper;
use Civi\GrassrootsPetition\Auth;
use Civi;
use Civi\Api4\GrassrootsPetitionCampaign;
use CRM_Grassrootspetition_ExtensionUtil as E;
use League\CommonMark\CommonMarkConverter;

class GrassrootsPetition extends InlayType {

  public static $typeName = 'Grassroots Petition';
  public static $customFieldsMap;
  public static $routes = [
    'GET' => [
      'publicData' => 'processGetPublicDataRequest',
      'publicPetitionList' => 'processGetPublicPetitionList',
    ],
    'POST' => [
      'submitSignature'    => 'processSubmitSignatureRequest',
      'adminAuthEmail'     => 'processAdminAuthEmail',
      'adminPetitionsList' => 'processAdminPetitionsList',
      'adminSavePetition'  => 'processAdminSavePetition',
      'adminLoadPetition'  => 'processAdminLoadPetition',
      'adminLoadUpdates'   => 'processAdminLoadUpdates',
      'adminAddUpdate'     => 'processAdminAddUpdate',
      'adminGetSignatures' => 'processAdminGetSignatures',
      'adminCreateMailing' => 'processAdminCreateMailing',
    ]
  ];

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
    'thanksMsgTplID'   => NULL,
    'confirmMsgTplID'   => NULL,
    // Socials v1.2 {{{
    'socials'            => ['twitter', 'facebook', 'email', 'whatsapp'],
    'socialStyle'        => 'col-buttons', // col-buttons|col-icon|'',
    'tweet'              => '',
    'whatsappText'       => '',
    // }}}
    'consentIntroHTML'   => '<p>Get emails about this campaign and from People & Planet on our current and future projects, campaigns and appeals. There’s a link to unsubscribe at the bottom of each email update. <a href="#">Privacy Policy</a></p>',
    'consentNoWarning'   => 'If you’re not already subscribed you won’t hear about the success (or otherwise!) of this campaign. Sure?',
    'thanksShareAskHTML' => '<h2>Thanks, please share this petition</h2><p>Thanks for signing. Can you share to help amplify your voice?</p>',
    'thanksFinalHTML'    => '<h2>Thanks, can you donate?</h2><p>Can you chip in to help People &amp; Planet’s campaigns?</p><p><a class="button primary" href="/donate">Donate</a></p>',
    'downloadPermissions' => [], // ['name', 'email', ... ]
    'allowMailings'      => FALSE,
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
    return \CRM_Queue_Service::singleton()->create([
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
   * Return a list of assets used by this Inlay.
   *
   * We do not need to include our bundle file; Inlay itself looks after that.
   */
  public function getAssets(): array {
    $campaignIDs = GrassrootsPetitionCampaign::get(FALSE)
      ->addSelect('id')->execute()->column('id');
    $assets = [];
    foreach ($campaignIDs as $campaignID) {
      $assets[] = "grassrootspetition_campaign_{$campaignID}_default_image";
    }
    return $assets;
  }

  /**
   * Generates data to be served with the Javascript application code bundle.
   *
   * Nb. this data is used for all petitions. So the tweet is the default tweet.
   *
   * @return array
   */
  public function getInitData(): array {
    $data = [
      // Name of global Javascript function used to boot this app.
      'init'             => 'inlayGrpetInit',
    ];

    // Socials v1.2 {{{
    $data['socialStyle'] = $this->config['socialStyle'] ?? '';
    $data['socials'] = [];
    foreach ($this->config['socials'] as $social) {
      $_ = ['name' => $social];
      if ($social === 'twitter') {
        $_['tweet'] = $this->config['tweet'];
      } elseif ($social === 'whatsapp') {
        $_['whatsappText'] = $this->config['whatsappText'];
      }
      $data['socials'][] = $_;
    }
    // }}}

    return $data;
  }

  /**
   * Process a request
   *
   * Request data is just key, value pairs from the form data. If it does not
   * have 'token' field then a token is generated and returned. Otherwise the
   * token is checked and processing continues.
   *
   * @param \Civi\Inlay\ApiRequest $request
   * @return array
   *
   * @throws \Civi\Inlay\ApiException;
   */
  public function processRequest(ApiRequest $request): array {

    $method = static::$routes[$request->getMethod()][$request->getBody()['need'] ?? ''] ?? NULL;
    if (empty($method)) {
      throw new ApiException(
        400,
        ['publicError' => 'Invalid request. (Routing error)'],
        "GrassrootsPetition API called with invalid 'need'"
      );
    }

    return $this->$method($request);
  }
  /**
   *
   * @return array
   */
  public function processGetPublicPetitionList(ApiRequest $request) {

    CaseWrapper::init();
    $petitions = CaseWrapper::getPetitionsForPublic();
    $output = ['petitions' => [], 'campaigns' => []];

    /** @var CaseWrapper $petition */
    foreach ($petitions as $petition) {
      $listOrder = $petition->getListOrder();
      if ($listOrder === 'unlisted') {
        // Skip this one.
        continue;
      }
      $mainImage = $petition->getMainImage();
      $public = [
        'id'             => $petition->getID(),
        'petitionTitle'  => $petition->getPetitionTitle(),
        'location'       => $petition->getCustomData('grpet_location'),
        'slug'           => $petition->getCustomData('grpet_slug'),
        'signatureCount' => $petition->getPetitionSigsCount(),
        'campaignID'     => (int) $petition->getCustomData('grpet_campaign'),
        'imageUrl'       => $mainImage['imageUrl'],
        'imageAlt'       => $mainImage['imageAlt'],
        'listOrder'      => $listOrder,
      ];
      if (!isset($output['campaigns'][$public['campaignID']])) {
        $output['campaigns'][$public['campaignID']] = $petition->getCampaign();
      }

      $output['petitions'][] = $public;
    }

    // Sort by: then campaign-is-active, then listOrder, then signatures.
    usort($output['petitions'], function ($a, $b) use ($output) {

      $campaignIsActiveA = $output['campaigns'][$a['campaignID']]['is_active'] ?? false;
      $campaignIsActiveB = $output['campaigns'][$b['campaignID']]['is_active'] ?? false;
      if ($campaignIsActiveA && !$campaignIsActiveB) {
        return -1;
      }
      if (!$campaignIsActiveA && $campaignIsActiveB) {
        return 1;
      }

      if ($a['listOrder'] === 'priority' && $b['listOrder'] !== 'priority') {
        return -1;
      }
      if ($a['listOrder'] !== 'priority' && $b['listOrder'] === 'priority') {
        return 1;
      }

      // Finally, most signatures at the top.
      return (($b['signatureCount'] ?? 0) <=> ($a['signatureCount'] ?? 0));
    });

    // Simplify/reduce campaigns output.
    foreach ($output['campaigns'] as &$campaign) {
      $campaign = [
        'id' => $campaign['id'],
        'label' => $campaign['label'],
      ];
    }


    return $output;
  }
  /**
   * Finds the petition and returns its definition, counts etc.
   *
   * @return array
   */
  public function processGetPublicDataRequest(ApiRequest $request) {
    $case = $this->getCaseWrapperFromRequest($request);
    // Extract what we need from the case.
    $return = ['publicData' => $case->getPublicData()];
    $return['publicData'] += [
      'consentIntroHTML'   => $this->config['consentIntroHTML'],
      'consentNoWarning'   => $this->config['consentNoWarning'],
      'thanksShareAskHTML' => $this->config['thanksShareAskHTML'],
      'thanksFinalHTML'    => $this->config['thanksFinalHTML'],
    ];
    return $return;
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

      $queue->createItem(new \CRM_Queue_Task(
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
   * @param array $data
   *
   * @return array
   */
  public function cleanupInput($data) {
    /** @var Array errors in this array, it will later be converted to a string. */
    $errors = [];
    /** @var Array Collect validated data in this array */
    $valid = [];

    $this->cleanupInputNameEmailPhone($data, $valid, $errors);

    // Optin.
    if (preg_match('/^(yes|no)$/', $data['optin'] ?? '')) {
      $valid['optin'] = $data['optin'];
    } else {
      $errors[] = "Please confirm consent for future communications.";
    }

    // Location should be the URL of the page.
    $valid['location'] = preg_replace('/[<>]+/', '', $data['location'] ?? '');

    $this->cleanupInputToken($data, $valid, $errors);
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
  public function getExternalScript(): string {

    $x = file_get_contents(E::path('dist/inlaygrpet.js'));
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
   * - grpet_what
   * - grpet_why
   * - grpet_sig_public
   * - grpet_sig_shared
   * - grpet_list_order
   * - grpet_sig_optin
   *
   * @param NULL|string
   * @return array|string
   */
  public static function getCustomFields($field = NULL) {
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
      throw new \Civi\Inlay\ApiException(
        400,
        ['error' => 'Petition not found'],
        "Failed to load case with ID " . json_encode($data['case_id'])
      );
    }
    $campaign = $case->getCampaign();

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
      $case->addSignedPetitionActivity($contactID, $data);
    }

    // They must agree the GRPR stuff when submitting the form.
    if (class_exists('CRM_Gdpr_SLA_Utils')) {
      \CRM_Gdpr_SLA_Utils::recordSLAAcceptance($contactID);
    }

    $msgTplID = NULL;
    if ($data['optin'] === 'yes') {
      // xxx @todo remove this hard coded value; implement in config. Also conseider per-campaign. (per petition even)
      $case->recordConsent($contactID, $data, $this->config['groupID'] ?? 62);

      // Select marketing-safe thank you email.
      $msgTplID = $case->getCustomData('grpet_thanks_msg_template_id');
      if (!$msgTplID) {
        Civi::log()->info("Did not find thanks_msg_template_id for petition " . $case->getID());
        $msgTplID = $campaign['thanks_msg_template_id'] ?? NULL;
      }
      if (!$msgTplID) {
        Civi::log()->info("Did not find thanks_msg_template_id for campaign " . json_encode($campaign));
        $msgTplID = $this->config['thanksMsgTplID'] ?? NULL;
      }
    } else {
      // No consent/opt-in, but do we still want to send a non-marketing confirmation
      $msgTplID = $case->getCustomData('grpet_confirm_msg_template_id');
      if (!$msgTplID) {
        $msgTplID = $campaign['confirm_msg_template_id'] ?? NULL;
      }
      if (!$msgTplID) {
        $msgTplID = $this->config['confirmMsgTplID'] ?? NULL;
      }
    }

    if ($msgTplID) {
      $this->sendMsgTpl($contactID, $data['email'], $msgTplID);
    }

    // No error
    return '';
  }

  /**
   * Email submitted to obtain a one-time login link.
   */
  protected function processAdminAuthEmail(ApiRequest $request) {
    $body = $request->getBody();
    if (empty($body['email']) || !filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
      return [
        'publicError' => 'Invalid email',
      ];
    }

    $letEmIn = FALSE;
    $petition = FALSE;

    if (preg_match('/^\d{1,10}$/', $body['petitionID'] ?? '')) {
      // Petition ID is sent.
      // This is a quick auth

      // Look up email, and whether they have any petitions.
      new CaseWrapper();
      $ids = CaseWrapper::getPetitionsOwnedByEmail($body['email'], (int) $body['petitionID']);
      \Civi::log()->info("Got " . json_encode(['email' => $body['email'], 'case' => $ids]));
      if (!empty($ids)) {
        $petition = $ids['caseID'];
        $contactID = $ids['contactID'];
        $letEmIn = TRUE;
      }
    } else {
      // Sign up.
      $valid = $this->cleanupSignupRequest($body);
      if (empty($valid['token'])) {
        // Unsigned request. Issue a token that will be valid in 5s time and lasts 2mins max.
        return ['token' => $this->getCSRFToken(['data' => $valid, 'validFrom' => 5, 'validTo' => 120])];
      }

      // Valid request with valid token.
      // Find/create contact
      // @todo record why they provided data?
      // @todo source?
      $params = array_intersect_key($valid, array_flip(
        ['first_name', 'last_name', 'email']
      )) + ['contact_type' => 'Individual'];
      // Add phone in, if given.
      if ($valid['phone']) {
        $params['phone'] = $valid['phone'];
      }
      $contactID = (int) civicrm_api3('Contact', 'getorcreate', $params)['id'] ?? 0;
      if (!$contactID) {
        Civi::log()->error('Failed to getorcreate contact with params: ' . json_encode($params));
        throw new \Civi\Inlay\ApiException(500, ['error' => 'Server error: XCM1a']);
      }
      $letEmIn = TRUE;
    }

    // Create contact hash, send auth email.
    if ($letEmIn) {
      // Valid for 1 hour.
      $hash = Auth::createAuthRecord($contactID, 60 * 60, 'T');
      if ($petition) {
        $hash = "P{$petition}-$hash";
      }
      $link = Civi::settings()->get('grpet_public_admin_url') . $hash;
      // Send email
      $msgTplID = civicrm_api3('MessageTemplate', 'getsingle', ['return' => 'id', 'msg_title' => 'Grassroots Petition Admin Link'])['id'];
      $this->sendMsgTpl($contactID, $body['email'], $msgTplID, [
        'petitionLink' => $link, /*Nb. use of template params requires Smarty (without my PR?).*/
      ]);
      Civi::log()->info("Grassroots Petition Auth Email $msgTplID sent to $contactID $body[email] with link $link");
    }

    // We always return successful so as not to give anything away about what data we hold.
    return ['success' => 1];
  }

  /**
   * Validate and clean up input data.
   *
   * @param array $data
   *
   * @return array
   */
  public function cleanupSignupRequest($data) {
    /** @var Array errors in this array, it will later be converted to a string. */
    $errors = [];
    /** @var Array Collect validated data in this array */
    $valid = [];
    $this->cleanupInputNameEmailPhone($data, $valid, $errors);
    $this->cleanupInputToken($data, $valid, $errors);
    return $valid;
  }

  /**
   * DRY code.
   *
   * @param array $data
   *
   * @return array
   */
  public function cleanupInputNameEmailPhone(array $data, array &$valid, array &$errors): void {

    // Names
    foreach (['first_name', 'last_name'] as $field) {
      try {
        $valid[$field] = static::requireSimpleText($data[$field], 50, $field);
      } catch (ApiException $e) {
        $errors[] = $e->responseObject['publicError'] ?? "Error on $field";
      }
    }

    // Email
    $email = trim($data['email']) ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors[] = "invalid email address";
    } else {
      $valid['email'] = $email;
    }

    // Check we've not been fed a web address as a name
    // (used by spammers who rely on "Dear {firstname}")
    // We check the concat in case they rely on first-last or last-first to form a url.
    // v1
    if (preg_match('#(www|http|[%@:/?$])#i', "$valid[first_name]$valid[last_name]$valid[first_name]")) {
      $errors[] = "Invalid name";
    }

    // Phone is optional
    $valid['phone'] = '';
    if (!empty($data['phone'])) {
      // Require at least 11 numbers.
      if (!preg_match('/[0-9]{11,}/', preg_replace('/[^0-9]+/', '', $data['phone']))) {
        $errors[] = "Your phone number does not look valid. (Nb. providing a phone is optional.)";
      } else {
        // Strip out everything that looks phoney. I mean non-phoney. I mean...
        $valid['phone'] = preg_replace('/[^0-9]+/', '', $data['phone']);
      }
    }
  }

  /**
   * DRY code. Adds token, or checks token.
   *
   * @param array $data
   */
  public function cleanupInputToken(array $data, array &$valid, array &$errors): void {

    if ($errors) {
      throw new \Civi\Inlay\ApiException(400, ['publicError' => implode(', ', $errors)]);
    }

    // Data is valid.
    if (!empty($data['token'])) {
      // There is a token, check that now.
      try {
        $this->checkCSRFToken($data['token'], $valid);
        $valid['token'] = TRUE;
      } catch (\InvalidArgumentException $e) {
        // Token failed. Issue a public friendly message, though this should
        // never be seen by anyone legit.
        Civi::log()->notice("Token error: " . $e->getMessage . "\n" . $e->getTraceAsString());
        throw new \Civi\Inlay\ApiException(
          400,
          ['error' => "Mysterious problem, sorry! Code " . substr($e->getMessage(), 0, 3)]
        );
      }
    }
  }


  /**
   * List petitions for the contact.
   *
   * We also output a list of campaigns that can create new petitions.
   */
  protected function processAdminPetitionsList(ApiRequest $request) {
    $response = ['success' => 1];
    $contactID = $this->checkAuthenticated($request, $response);

    // $cases = CaseWrapper::getPetitionsOwnedByContact($contactID);

    // Summarise the cases
    $response['petitions'] = $this->getListOfPetitionsForContact($contactID);

    $response['campaigns'] = [];
    $campaigns = GrassrootsPetitionCampaign::get(FALSE)
      ->setCheckPermissions(FALSE)
      ->addWhere('is_active', '=', TRUE)
      ->execute();
    foreach ($campaigns as $campaign) {
      $response['campaigns'][] = array_intersect_key($campaign, array_flip([
        'label', 'description', 'template_what', 'template_why', 'template_title', 'template_tweet',
      ]));
    }

    return $response;
  }
  /**
   * List petitions for the contact.
   */
  protected function getListOfPetitionsForContact(int $contactID): array {
    $cases = CaseWrapper::getPetitionsOwnedByContact($contactID);

    // Summarise the cases
    $list = [];
    foreach ($cases as $petition) {
      $mainImage = $petition->getMainImage();
      $list[] = [
        'id'                  => $petition->getID(),
        'title'               => $petition->getPetitionTitle(),
        'status'              => $petition->getCaseStatus(),
        'location'            => $petition->getCustomData('grpet_location'),
        'slug'                => $petition->getCustomData('grpet_slug'),
        'targetCount'         => $petition->getCustomData('grpet_target_count'),
        'imageAlt'            => $mainImage['imageAlt'],
        'imageUrl'            => $mainImage['imageUrl'],
        'targetName'          => $petition->getCustomData('grpet_target_name'),
        'campaign'            => $petition->getCampaignPublicName(),
        'signatureCount'      => $petition->getPetitionSigsCount(),
        'downloadPermissions' => !empty($petition->getDownloadPermissions() ?? $this->config['downloadPermissions']),
        'allowMailings'       => $petition->getMailingPermissions() ?? $this->config['allowMailings'],
      ];
    }

    return $list;
  }
  /**
   * Save petition.
   *
   * We also output updated list of petitions.
   */
  protected function processAdminSavePetition(ApiRequest $request) {
    $response = [];
    $contactID = $this->checkAuthenticated($request, $response);

    $body = $request->getBody();

    // Identify the CaseID (0 = new, >0 = editing existing) and the campaign.
    $caseID = (int) ($body['id'] ?? 0);
    if ($caseID) {
      // Editing an existing petition.
      // Check that it belongs to this person.
      $case = CaseWrapper::getPetitionsOwnedByContact($contactID, $caseID)[0] ?? NULL;
      if (!$case) {
        // This case does not exist, or is not owned by this contact.
        throw new ApiException(400, ['publicError' => 'Invalid request. (IVP1)'], "Contact $contactID tried to save case $caseID which does not belong to them.");
      }
      // We know that $caseID is valid, and $case is a CaseWrapper.

      // Fix campaign, this is not allowed to change.
      $campaign = $case->getCampaign();
    } else {
      // We require the campaignLabel for new petitions.
      if (!is_string($body['campaignLabel'] ?? NULL)) {
        throw new ApiException(400, ['publicError' => 'Invalid request. (IVP2)'], "Contact $contactID tried to save case with missing/nonstring campaignLabel.");
      }
      $campaign = GrassrootsPetitionCampaign::get(FALSE)
        ->addWhere('label', '=', $body['campaignLabel'])
        ->addWhere('is_active', '=', TRUE)
        ->execute()->first();
      if (!$campaign) {
        throw new ApiException(400, ['publicError' => 'Invalid request. (IVP3)'], "Contact $contactID tried to save case with invalid campaignLabel '$body[campaignLabel]'.");
      }
    }

    // Validate the data into the $valid array.
    $valid = [];
    // For create AND for edit we need these:
    $valid['why'] = $this->requireSimpleText($body['why'] ?? '', 50000, "why", TRUE);
    $valid['title'] = $this->requireSimpleText($body['title'] ?? '', 255, "title");
    $valid['who'] = $this->requireSimpleText($body['who'] ?? '', 255, "who");
    $valid['targetCount'] = (int)($body['targetCount'] ?? 0);
    if (!($valid['targetCount'] > 0)) {
      throw new ApiException(400, ['publicError' => 'Target count must be a number.']);
    }

    if (!$caseID) {
      // We need more data for a new petition.
      $valid['targetName'] = $this->requireSimpleText($body['targetName'] ?? '', 255, "target name");
      $valid['location'] = $this->requireSimpleText($body['location'] ?? '', 255, "location");
      $valid['what'] = $this->requireSimpleText($body['what'] ?? '', 50000, "what", TRUE);
      $valid['campaign'] = $campaign['label'];
    }

    // OK, if we get here, we have all we need in $valid. Copy the bits we need
    // into an $updates array.
    $updates = [];
    if ($caseID) {
      // Existing case. Update only the bits we're allowing.
      $updates += [
        'grpet_who'          => $valid['who'],
        'grpet_why'          => $valid['why'],
        'grpet_target_count' => $valid['targetCount'],
      ];
    } else {
      // New case, create it now.
      $case = CaseWrapper::createNew(
        $contactID,
        $valid['title'],
        $campaign['label'],
        $valid['location'],
        $valid['targetName'],
        $valid['who']
      );
      // These are the things you're NOT allowed to change later.
      $updates += [
        'grpet_what'         => $valid['what'],
        'grpet_why'          => $valid['why'],
        'grpet_target_count' => $valid['targetCount'],
      ];
    }
    // These functions don't do updates unless they're necessary.
    $case->setPetitionTitle($valid['title']);
    $case->setCustomData($updates);

    if (preg_match('@^data:(image/(?:jpeg|png));base64(.*)$@', $body['imageData'] ?? '', $m)) {
      // An image was sent.
      // Not sure if this frees RAM or not.
      unset($body['imageData']);
      $imageData = base64_decode($m[2]);
      $imageFileType = $m[1];
      unset($m);
      Civi::log()->debug("processAdminSavePetition Got image bytes: " . strlen($imageData));
      if ($imageData) {
        // We need to add our image to the case.
        $case->setMainImageFromImageData($imageData, $imageFileType);
      }
    }

    if (!$caseID) {
      // This was a new petition.
      // xxx move domain to config.
      $msgTplID = civicrm_api3('MessageTemplate', 'getsingle', ['return' => 'id', 'msg_title' => 'Grassroots Petition Login Link'])['id'];
      $this->sendMsgTpl($contactID, NULL, $msgTplID, [
        'loginLink'  => Civi::settings()->get('grpet_public_admin_url') . 'P' . $case->getID(),
        'publicLink' => Civi::settings()->get('grpet_public_url') . $case->getCustomData('grpet_slug'),
      ]);

      Civi::log()->info("Grassroots Petition Admin Login Link email ($msgTplID) sent to $contactID $body[email]");
    }

    // Done.
    return ['success' => 1, 'petitions' => $this->getListOfPetitionsForContact($contactID)];
  }

  /**
   * Send email.
   */
  public function sendMsgTpl(int $contactID, ?string $toEmail, int $msgTplID, array $tplVars = []) {

    $from = civicrm_api3('OptionValue', 'getvalue', ['return' => "label", 'option_group_id' => "from_email_address", 'is_default' => 1]);
    if ($toEmail === NULL) {
      // Look up primary email.
      $toEmail = civicrm_api3('Email', 'get', [
        'contact_id' => $contactID,
        'on_hold' => 0,
        'sequential' => 1,
        'return' => ['email'],
        'options' => ['sort' => "is_primary DESC"],
      ])['values'][0]['email'] ?? NULL;
      if (!$toEmail) {
        throw new ApiException(
          500,
          ['publicError' => 'Sorry, we don’t have a valid email for you. Please contact us. (EM1)'],
          "GrassrootsPetition: No valid email for contact $contactID, cannot send msgtpl $msgTplID"
        );
      }
    }

    // We use the "to" email sent in the data, as that's what they'd expect.
    $params = [
      'id'             => $msgTplID,
      'from'           => $from,
      'to_email'       => $toEmail,
      'contact_id'     => $contactID,
      // 'disable_smarty' => 1,
      'template_params' => $tplVars,
      /*
      'template_params' =>
      [ 'foo' => 'hello',
      // {$foo} in templates 'bar' => '123',
      // {$bar} in templates ],
      */
    ];

    try {
      civicrm_api3('MessageTemplate', 'send', $params);
    } catch (\Exception $e) {
      // Log silently.
      Civi::log()->error("Failed to send MessageTemplate with params: " . json_encode($params, JSON_PRETTY_PRINT) . " Caught " . get_class($e) . ": " . $e->getMessage());
    }
  }
  /**
   * Load petition details for editing.
   */
  protected function processAdminLoadPetition(ApiRequest $request) {
    $response = [];
    $contactID = $this->checkAuthenticated($request, $response);

    $body = $request->getBody();
    $caseID = (int) ($body['petitionID'] ?? 0);
    if (!$caseID) {
      throw new ApiException(400, ['publicError' => 'Invalid request. (LP2)'], "Contact $contactID tried made adminLoadPetition request without id");
    }
    // Editing an existing petition.
    // Check that it belongs to this person.
    $case = CaseWrapper::getPetitionsOwnedByContact($contactID, $caseID)[0] ?? NULL;
    if (!$case) {
      // This case does not exist, or is not owned by this contact.
      throw new ApiException(400, ['publicError' => 'Invalid request. (IVP1)'], "Contact $contactID tried to save case $caseID which does not belong to them.");
    }

    $mainImage = $case->getMainImage();
    $data = [
      'id'               => $case->getID(),
      'title'            => $case->getPetitionTitle(),
      'targetName'       => $case->getCustomData('grpet_target_name'),
      'who'              => $case->getCustomData('grpet_who'),
      'what'             => $case->getWhat(FALSE), // get raw markdown
      'why'              => $case->getWhy(FALSE),  // get raw markdown
      'targetCount'      => $case->getCustomData('grpet_target_count'),
      'location'         => $case->getCustomData('grpet_location'),
      // campaigLabel not needed.

      'slug'             => $case->getCustomData('grpet_slug'),

      'imageUrl'         => $mainImage['url'],
      'imageAlt'         => $mainImage['alt'],
      'tweet'            => $case->getCustomData('grpet_tweet_text'),
      'status'           => $case->getCaseStatus(),
    ];

    // Download permissions
    $data['downloadPermissions'] = !empty($case->getDownloadPermissions() ?? $this->config['downloadPermissions']);

    // Mailing permissions
    $data['allowMailings'] = $case->getMailingPermissions() ?? $this->config['allowMailings'];

    // Done.
    return ['success' => 1, 'petition' => $data];
  }
  /**
   * Load petition updates for editing.
   */
  protected function processAdminLoadUpdates(ApiRequest $request) {
    $response = [];
    $contactID = $this->checkAuthenticated($request, $response);

    $body = $request->getBody();
    $caseID = (int) ($body['petitionID'] ?? 0);
    if (!$caseID) {
      throw new ApiException(400, ['publicError' => 'Invalid request. (LP2)'], "Contact $contactID tried made adminLoadPetition request without id");
    }
    // Editing an existing petition.
    // Check that it belongs to this person.
    $case = CaseWrapper::getPetitionsOwnedByContact($contactID, $caseID)[0] ?? NULL;
    if (!$case) {
      // This case does not exist, or is not owned by this contact.
      throw new ApiException(400, ['publicError' => 'Invalid request. (IVP1)'], "Contact $contactID tried to save case $caseID which does not belong to them.");
    }

    $updates = $case->getAdminUpdates();

    // We return all updates except cancelled.
    return ['success' => 1, 'updates' => $updates];
  }
  /**
   * Create an update activity.
   */
  protected function processAdminAddUpdate(ApiRequest $request) {
    $response = [];
    $contactID = $this->checkAuthenticated($request, $response);

    $body = $request->getBody();

    $caseID = (int) ($body['petitionID'] ?? 0);
    $case = NULL;
    if ($caseID) {
      // Editing an existing petition.
      // Check that it belongs to this person.
      $case = CaseWrapper::getPetitionsOwnedByContact($contactID, $caseID)[0] ?? NULL;
    }
    if (!$case) {
      // This case does not exist, or is not owned by this contact.
      throw new ApiException(400, ['publicError' => 'Invalid request. (AA1)'], "Contact $contactID tried to add update to case $caseID which does not belong to them/does not exist.");
    }

    // Status change?
    if ($case->getCaseStatus() !== 'grpet_Pending') {
      // Only allow updating if it's not pending.
      $case->setStatus($body['status']);
    }

    if (trim($body['text'])) {
      // Convert to HTML
      $text = '<p>'
        . preg_replace(
          '/[\r\n]+/',
          '</p><p>',
          htmlspecialchars($this->requireSimpleText($body['text'] ?? '', 50000, "text"))
        )
        . '</p>';
      $subject = '';
      $activityID = $case->addUpdateActivity(
        $contactID,
        $subject,
        $text
      );

      // Image?
      if (preg_match('@^data:(image/(?:jpeg|png));base64(.*)$@', $body['imageData'] ?? '', $m)) {
        // An image was sent.
        // Not sure if this frees RAM or not.
        unset($body['imageData']);
        $imageData = base64_decode($m[2]);
        $imageFileType = $m[1];
        unset($m);
        if ($imageData) {
          $case->addImageToUpdateActivityFromData($activityID, $imageData, $imageFileType);
        }
      }
    }

    // Done.
    return ['success' => 1, 'petitions' => $this->getListOfPetitionsForContact($contactID)];
  }
  /**
   * Download CSV of signatures
   */
  protected function processAdminGetSignatures(ApiRequest $request) {
    $response = [];
    $contactID = $this->checkAuthenticated($request, $response);

    $body = $request->getBody();
    $caseID = (int) ($body['petitionID'] ?? 0);
    $case = NULL;
    if ($caseID) {
      // Editing an existing petition.
      // Check that it belongs to this person.
      $case = CaseWrapper::getPetitionsOwnedByContact($contactID, $caseID)[0] ?? NULL;
    }
    if (!$case) {
      // This case does not exist, or is not owned by this contact.
      throw new ApiException(400, ['publicError' => 'Invalid request. (AGS1)'], "Contact $contactID tried to get signatures for case $caseID which does not belong to them/does not exist.");
    }

    $permissions = $case->getDownloadPermissions() ?? $this->config['downloadPermissions'];
    if (empty($permissions)) {
      throw new ApiException(400, ['publicError' => 'Permission denied (DWN1)'], "Contact $contactID tried to get signatures for case $caseID but download permissions are not configured to allow this.");
    }
    $columns = [
      ['"Date"', 'activity_date_time', FALSE],
    ];
    foreach ($permissions as $perm) {
      $columns[] = [
        [
          'name' => '"Name"',
          'email' => '"Email"',
        ][$perm],
        [
          'name' => 'contact.display_name',
          'email' => 'email.email',
        ][$perm],
        TRUE
      ];
    }

    /** @var CaseWrapper $case */
    $signatures = $case->getPetitionSigs($permissions);

    $csv = implode(',', array_column($columns, 0)) . "\n";
    foreach ($signatures as $signer) {
      $row = [];
      foreach ($columns as $col) {
        $field = $col[1];
        $needsQuoting = $col[2];
        $val = $signer[$field];
        if ($field === 'email.email') {
          $val = preg_replace('/^(.)[^@]*(@.*)$/', '$1******$2', $val);
        }
        if ($needsQuoting) {
          $val = str_replace('"', '""', $val);
        }
        $row[] = $val;
      }
      $csv .= implode(',', $row) . "\n";
    }

    // Done.
    return ['success' => 1, 'csv' => $csv];
  }
  /**
   * Create a mailing
   */
  protected function processAdminCreateMailing(ApiRequest $request): array {
    $response = [];
    $contactID = $this->checkAuthenticated($request, $response);

    $body = $request->getBody();
    $caseID = (int) ($body['petitionID'] ?? 0);
    $case = NULL;
    if ($caseID) {
      // Check that it belongs to this person.
      /** @var CaseWrapper */
      $case = CaseWrapper::getPetitionsOwnedByContact($contactID, $caseID)[0] ?? NULL;
    }
    if (!$case) {
      // This case does not exist, or is not owned by this contact.
      throw new ApiException(400, ['publicError' => 'Invalid request. (CM1)'], "Contact $contactID tried to create mailing for case $caseID which does not belong to them/does not exist.");
    }

    if (empty($case->getMailingPermissions() ?? $this->config['allowMailings'])) {
      throw new ApiException(400, ['publicError' => 'Permission denied (CM2)'], "Contact $contactID tried to create mailing for case $caseID but creating maliing is not allowed.");
    }

    // Ensure we have valid input.
    $subject = static::requireSimpleText($body['emailSubject'], 200, 'subject', FALSE, TRUE);
    $body = static::requireSimpleText($body['emailBody'], 4000, 'message body', TRUE, TRUE);

    $markdownConverter = new CommonMarkConverter([
      'html_input'         => 'strip',
      'allow_unsafe_links' => false,
    ]);

    $mailingGroupID = $case->getSignerMailingList();
    // Create CiviMail mailing
    $domainContactID = \CRM_Core_BAO_Domain::getDomain()->contact_id;

    // Load the petition owner's name
    $ownerName = \Civi\Api4\Contact::get(FALSE)->addWhere('id', '=', $case->getOwnerContactID())->addSelect('display_name')->execute()->first()['display_name'];

    list($domainFromName, $domainFromEmail) = \CRM_Core_BAO_Domain::getNameAndEmail(TRUE);
    list($headerID, $footerID) = $this->getMailingHeaderFooterIDs();
    $mailing = civicrm_api3('Mailing', 'create', [
      'name'           => "Grassroots Petition (#$caseID) update: " . $case->getPetitionTitle(),
      'from_name'      => $domainFromName, /* or $ownerName - but this then fails in the mailing screen which uses a select from registered addresses */
      'from_email'     => $domainFromEmail,
      'header_id'      => $headerID,
      'footer_id'      => $footerID,
      'created_id'     => $case->getOwnerContactID(),
      'scheduled_id'   => NULL,
      'scheduled_date' => NULL,
      'approval_date'  => NULL,
      'body_html'      => $markdownConverter->convertToHtml($body),
      'body_text'      => $body,
      'subject'        => $subject,
      'visibility'     => 'User and User Admin Only',
      'groups' => [
        'include' => [$mailingGroupID],
        // 'exclude' => [],
        // 'base' => [$mailingGroupID],
      ],
      // 'replyto_email'
      //'template_type' => $templateTypes[0]['name'],
    ]);
    Civi::log()->notice("Created mailing $mailing[id] for petition (case) $caseID owned by '$ownerName'. " . $case->getPetitionTitle());

    $campaign = $case->getCampaign();
    if (!empty($campaign['notify_contact_id'])) {

      // Nb. the MessageTemplate API won't let you search by name(!)
      $msgTplID = civicrm_api3('MessageTemplate', 'getsingle', ['return' => 'id', 'msg_title' => 'Grassroots Petition New Mailing Notification'])['id'];

      $toEmail = $campaign['notify_email'] ?? NULL;

      $from = civicrm_api3('OptionValue', 'getvalue', ['return' => "label", 'option_group_id' => "from_email_address", 'is_default' => 1]);
      if ($toEmail === NULL) {
        // Look up primary email.
        $toEmail = civicrm_api3('Email', 'get', [
          'contact_id' => $campaign['notify_contact_id'],
          'on_hold' => 0,
          'sequential' => 1,
          'return' => ['email'],
          'options' => ['sort' => "is_primary DESC"],
        ])['values'][0]['email'] ?? NULL;
        if (!$toEmail) {
          throw new ApiException(
            500,
            ['publicError' => 'Sorry, this petition is misconfigured. Please contact us. (MLG1)'],
            "GrassrootsPetition: Notify contact ({$campaign['notify_contact_id']}) has no valid email, "
              . "cannot notify about new petition mailing (CaseID " . $case->getID() . "), though one has been created."
          );
        }
      }

      $params = [
        'id'             => $msgTplID,
        'from'           => $from,
        'to_email'       => $toEmail,
        'contact_id'     => $campaign['notify_contact_id'],
        // 'disable_smarty' => 1,
        'template_params' => [
          'campaignName' => $campaign['label'],
          'petitionName' => $case->getPetitionTitle(),
          'petitionOwner' => $ownerName,
        ]
      ];

      try {
        civicrm_api3('MessageTemplate', 'send', $params);
      } catch (\Exception $e) {
        throw new ApiException(
          500,
          ['publicError' => 'Sorry, there was a problem notifying staff about the new petition. Please contact us. (EM3)'],
          "GrassrootsPetition: Failed to send notification of new petitoin email: " . json_encode($params) . " on case "
            . $case->getID() . " Exception message: " . $e->getMessage()
        );
      }
    }
    return ['success' => 1];
  }
  /**
   *
   */
  protected function getMailingHeaderFooterIDs(): array {
    return [
      civicrm_api3('MailingComponent', 'get', [
        'return' => 'id',
        'name' => "Header for Grassroots Petition updates",
        'component_type' => 'Header',
        'is_active' => 1,
      ])['id'] ?? NULL,
      civicrm_api3('MailingComponent', 'get', [
        'return' => 'id',
        'name' => "Footer for Grassroots Petition updates",
        'component_type' => 'Footer',
        'is_active' => 1,
      ])['id'] ?? NULL,
    ];
  }
  /**
   * Returns the authenticated contactID, or throws a 401 ApiException
   */
  protected function checkAuthenticated(ApiRequest $request, array &$response): int {
    $result = Auth::checkAuthRecord($request->getBody()['authToken'] ?? '');
    if (!$result['contactID']) {
      throw new \Civi\Inlay\ApiException(401, ['error' => 'Unauthorised']);
    }
    if (!empty($result['token'])) {
      $response['token'] = $result['token'];
    }
    return $result['contactID'];
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
    $activityTypeID = CaseWrapper::getActivityTypeIDByName('Grassroots Petition signed');

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
  public function addSignedPetitionActivity(int $contactID) {

    $activityCreateParams = [
      'activity_type_id'     => CaseWrapper::getActivityTypeIDByName('Grassroots Petition signed'),
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
  protected function getCaseWrapperFromRequest(ApiRequest $request): CaseWrapper {
    $rawInput = $request->getBody();
    $slug = $rawInput['petitionSlug'] ?? '';

    // We assume nginx has done some caching so if we're called here we need to do all the work again.

    // The slug is stored as custom data on the case.
    $case = CaseWrapper::fromSlug($slug);
    if (!$case) {
      throw new ApiException(400, ['publicError' => 'Petition not ' . $slug . ' found. If you have just created a petition, it will be available as soon as it has been moderated by staff.']);
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
  /**
   * Checks a string that we expect to be text for anything dodgy.
   *
   * @throws ApiException
   */
  public static function requireSimpleText($text, ?int $maxLength = NULL, string $publicFieldName = '', bool $allowLinks = FALSE, bool $allowEmoji = FALSE): string {
    if (empty($text) || !is_string($text) || trim($text) === '') {
      throw new ApiException(
        400,
        ['publicError' => "Invalid $publicFieldName. (ST1)"],
        "$publicFieldName failed validation"
      );
    }
    // Is a string.
    $text = trim($text);
    $disallowed = $allowLinks ? '@[<>]@' : '@([<>]|http|//)@';
    if (preg_match($disallowed, $text)) {
      $hints = ['Certain special characters are not allowed.'];
      if (!$allowLinks) {
        $hints[] = 'Links are not allowed.';
      }
      if (!$allowEmoji) {
        $hints[] = '😢 Emoji are not allowed.';
      }
      $hints = implode(' ', $hints);
      throw new ApiException(
        400,
        ['publicError' => "Invalid $publicFieldName: $hints (ST2)"],
        "$publicFieldName contains special chars or http"
      );
    }
    // Emojis? No thanks.
    if (!$allowEmoji && preg_match("/[\u{1f300}-\u{1f5ff}\u{e000}-\u{f8ff}]/u", $text)) {
      throw new ApiException(
        400,
        ['publicError' => "Invalid $publicFieldName, 😥 emojis are not allowed (ST3)"],
        "$publicFieldName contains emojis"
      );
    }
    if ($maxLength && mb_strlen($text) > $maxLength) {
      throw new ApiException(
        400,
        ['publicError' => "Invalid $publicFieldName, too long (ST4)"],
        "$publicFieldName contains emojis"
      );
    }

    return $text;
  }
}
