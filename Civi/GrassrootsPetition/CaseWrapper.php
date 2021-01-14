<?php
namespace Civi\GrassrootsPetition;

use Civi\Inlay\GrassrootsPetition;
use CRM_Core_DAO;
use Civi\Api4\OptionValue;
use Civi\Api4\GrassrootsPetitionCampaign;
use Civi;

/**
 * Various sugar and convenience functions wrapping a Case of type GrassrootsPetition
 */
class CaseWrapper {

  /** @var array holds the api3 case.get output */
  public $case;

  /** @var array Cache of the civicrm_grpet_campaign row data */
  public $campaign;

  /** @var Array */
  public static $activityTypesByName;

  /** @var array Status name to option value */
  public static $activityStatuses;
  /** @var array Status name to option value */
  public static $caseStatuses;
  /**
   * @var array of CaseWrapper objects keyed by Case ID. This will speed up
   * processing the same case over and over, e.g. when batch processing
   * submissions from a queue.
   */
  public static $instanceCache = [];
  /**
   * Instantiate an object from the slug
   *
   * @return CaseWrapper
   */
  public static function fromSlug(string $slug) :?CaseWrapper {
    $inlay = new GrassrootsPetition();
    $params = [ $inlay->getCustomFields('grpet_slug') => $slug, 'sequential' => 1 ];
    $cases = civicrm_api3('Case', 'get', $params);
    if ($cases['count'] == 1) {
      $case = new static();
      return $case->loadFromArray($cases['values'][0]);
    }
    return NULL;
  }

  /**
   * Load a (cached) instance.
   *
   * If reset is given, cache is not used.
   *
   * @return NULL|CaseWrapper
   */
  public static function fromID(int $id, bool $reset=FALSE) :?CaseWrapper {
    if (!$reset && isset(static::$instanceCache[$id])) {
      return static::$instanceCache[$id];
    }
    // This gets looked up later, if needed.
    $cases = civicrm_api3('Case', 'get', ['id' => $id]);
    if ($cases['count'] == 1) {
      $case = new static();
      $case->loadFromArray($cases['values'][$id]);
      // Cache it.
      static::$instanceCache[$id] = $case;
      return $case;
    }
    // Not found.
    return NULL;
  }

  public function __construct() {
    if (!isset(static::$activityTypesByName)) {
      // Look these up once now.
      static::$activityTypesByName = OptionValue::get(FALSE)
          ->setCheckPermissions(FALSE)
          ->addWhere('option_group_id:name', '=', 'activity_type')
          ->addWhere('name', 'IN', [
              'Grassroots Petition created',
              'Grassroots Petition progress',
              'Grassroots Petition mailing',
              'Grassroots Petition signed',
            ])
          ->execute()
          ->indexBy('name')->getArrayCopy();
    }

    if (!isset(static::$activityStatuses)) {
      // Create map activity status name => option value (status_id)
      static::$activityStatuses = [];
      $r = OptionValue::get(FALSE)
          ->setCheckPermissions(FALSE)
          ->addWhere('option_group_id:name', '=', 'activity_status')
          ->addWhere('is_active', '=', 1)
          ->execute()->indexBy('name');
      // Check ones we require.
      foreach (['Completed', 'grpet_pending_moderation', 'Cancelled', 'Scheduled'] as $requiredStatus) {
        $_ = $r[$requiredStatus] ?? NULL;
        if (!$_) {
          throw new \RuntimeException("Missing required '$requiredStatus' activity status.");
        }
        static::$activityStatuses[$_['name']] = $_['value'];
      }
    }

    if (!isset(static::$caseStatuses)) {
      // Create map activity status name => option value (status_id)
      static::$caseStatuses = [];
      $r = OptionValue::get(FALSE)
          ->setCheckPermissions(FALSE)
          ->addWhere('option_group_id:name', '=', 'case_status')
          ->addWhere('is_active', '=', 1)
          ->execute()->indexBy('name');
      // Check ones we require.
      foreach (['grpet_Pending', 'Open', 'grpet_Dead', 'grpet_Won'] as $requiredStatus) {
        $_ = $r[$requiredStatus] ?? NULL;
        if (!$_) {
          throw new \RuntimeException("Missing required '$requiredStatus' case status.");
        }
        static::$caseStatuses[$_['name']] = $_['value'];
      }
    }
  }
  /**
   *
   * @return CaseWrapper
   */
  public function loadFromArray($data) {
    $this->case = $data;
    // This gets looked up later, if needed.
    $this->campaign = NULL;
    return $this;
  }

  /**
   * Return the data needed to present the petition.
   *
   * @return array
   */
  public function getPublicData() {
    $public = [
      'status'         => $this->getCaseStatus(),
      'location'       => $this->getCustomData('grpet_location'),
      'slug'           => $this->getCustomData('grpet_slug'),
      'targetCount'    => $this->getCustomData('grpet_target_count'),
      'targetName'     => $this->getCustomData('grpet_target_name'),
      'tweet'          => $this->getCustomData('grpet_tweet_text'),
      'petitionTitle'  => $this->case['subject'] ?? '',
      'petitionHTML'   => $this->case['details'] ?? '',
      'campaign'       => $this->getCampaignPublicName(),
      'image'          => $this->getPetitionImageURL(), /* @todo */
      'updates'        => $this->getPetitionUpdates(),
      'signatureCount' => $this->getPetitionSigsCount(),
      // @todo expose these on the Inlay Config
      'consentIntroHTML' => '<p>Get emails about this campaign and from People & Planet on our current and future projects, campaigns and appeals. There’s a link to unsubscribe at the bottom of each email update. <a href="https://peopleandplanet.org/privacy">Privacy Policy</a></p>',
      'consentYesText'   => 'Yes please',
      'consentNoText'    => 'No, don’t add me',
      'consentNoWarning' => 'If you’re not already subscribed you won’t hear about the success (or otherwise!) of this campaign. Sure?',
      'thanksShareAsk'   => '<h2>Thanks, please share this petition</h2><p>Thanks for signing. Can you share to help amplify your voice?</p>',
      'thanksDonateAsk'  => '<h2>Thanks, can you donate?</h2><p>Can you chip in to help People &amp; Planet’s campaigns?</p><p><a class="button primary" href="/donate">Donate</a></p>',
    ];

    return $public;
  }

  /**
   * Return value of custom field.
   *
   */
  public function getCustomData(string $field) {
    $inlay = new GrassrootsPetition();
    return $this->case[$inlay->getCustomFields($field)] ?? NULL;
  }

  /**
   * Get petition HTML from the Open Case activity.
   */
  public function getPetitionHTML() {

  }

  /**
   * Get the Campaign Name
   */
  public function getCampaignAdminName() {
    $this->getCampaign()['name'];
  }
  /**
   * Get the public Campaign Name
   */
  public function getCampaignPublicName() {
    return $this->getCampaign()['label'];
  }
  /**
   * Get the Campaign data (getter for $this->campaign)
   */
  public function getCampaign() :?Array {
    if (!isset($this->campaign)) {
      $this->mustBeLoaded();

      $campaignID = $this->getCustomData('grpet_campaign');
      if (!$campaignID) {
        // e.g. new ones?
        // We don't cache this.
        return NULL;
      }

      // Look up campaign.
      $this->campaign = GrassrootsPetitionCampaign::get(FALSE)
        ->setCheckPermissions(FALSE)
        ->addWhere('id', '=', $campaignID)
        ->execute()->first();
    }
    return $this->campaign;
  }
  /**
   * @todo
   */
  public function getPetitionImageURL() {

  }
  /**
   * Updates are activities of the 'Grassroots Petition progress' type
   */
  public function getPetitionUpdates() {
    $this->mustBeLoaded();
    $caseID = (int) $this->case['id'];

    $updateActivityTypeID = (int) $this->activityTypes['Grassroots Petition update']['id'];
    $validStatuses = [static::$activityStatuses['Completed']];
    // @todo allow drafts?
    $validStatuses = implode(', ', $validStatuses);

    // Load these with SQL, as Activities and Api4 are difficult.
    $sql = "
      SELECT a.id, a.activity_date_time, a.subject, a.details
        FROM civicrm_activity a
        INNER JOIN civicrm_case_activity ca ON a.id = ca.activity_id AND ca.case_id = $caseID
      WHERE a.activity_type_id = $updateActivityTypeID
        AND a.status_id IN ($validStatuses)
      ORDER BY a.activity_date_time
    ";

    $updates = [];
    $dao = CRM_Core_DAO::executeQuery($sql);
    while ($dao->fetch()) {
      $updates[] = $dao->toArray();
    }
    // @todo images (attachments)

    return $updates;
  }
  /**
   * Returns a count of the signatures on a particular case.
   *
   * Optionally, check for a particular contact.
   */
  public function getPetitionSigsCount(?int $contactID=NULL) :int {
    $this->mustBeLoaded();
    // Count the 'Grassroots Petition signed' petitions.
    $signedActivityTypeID = (int) static::$activityTypesByName['Grassroots Petition signed']['value'];
    if (!$signedActivityTypeID) {
      throw new \RuntimeException("Failed to identify Grassroots Petition signed activity type. Check installation.");
    }

    $caseID = (int) $this->case['id'];
    $andIsSelectedContact = $contactID ? "AND c.id = $contactID" : '';

    // Count signed activities on this case from live contacts (i.e. exclude deleted contacts).
    $sql = "
      SELECT COUNT(*)
      FROM civicrm_activity a
      INNER JOIN civicrm_case_activity ca ON a.id = ca.activity_id AND ca.case_id = $caseID
      INNER JOIN civicrm_activity_contact ac ON ac.activity_id = a.id AND ac.record_type_id = 3 /* target */
      INNER JOIN civicrm_contact c ON ac.contact_id = c.id AND c.is_deleted = 0
      WHERE a.activity_type_id = $signedActivityTypeID
        AND a.is_deleted = 0
        $andIsSelectedContact
    ";
    $count = (int) CRM_Core_DAO::singleValueQuery($sql);

    // $count+= rand(23,76); // xxx todo
    return $count;
  }
  /**
   * Return the name of the case status from its value.
   *
   * N.b. the names are:
   * - 'grpet_Pending'
   * - 'Open'
   * - 'grpet_Dead'
   * - 'grpet_Won'
   */
  public function getCaseStatus() :string {
    $this->mustBeLoaded();
    return array_flip(static::$caseStatuses)[$this->case['status_id']];
  }
  /**
   * Get Case ID.
   */
  public function getID() :int {
    $this->mustBeLoaded();
    return (int) $this->case['id'];
  }
  /**
   * Get Case Subject (petition title)
   */
  public function getPetitionTitle() :string {
    $this->mustBeLoaded();
    return (string) $this->case['subject'] ?? '';
  }
  /**
   * Add a signed petition activity to the case for the given contact.
   *
   * @return int Activity ID created.
   */
  public function addSignedPetitionActivity(int $contactID, array $data) :int {

    $activityCreateParams = [
      'activity_type_id'     => static::$activityTypesByName['Grassroots Petition signed']['value'],
      'target_id'            => $contactID,
      'source_contact_id'    => $contactID,
      'subject'              => $this->case['subject'], /* Copy case subject (petition title)  */
      'status_id'            => 'Completed',
      'case_id'              => $this->case['id'],
      'location'             => $data['location'],
      // xxx move this to custom field.
      'details'              => ($data['optin'] === 'yes') ? '<p>Opted in to updates</p>' : '<p>Did not opt in</p>',
    ];
    $result = civicrm_api3('Activity', 'create', $activityCreateParams);

    return (int) $result['id'];
  }
  /**
   * Handle consent.
   *
   * This is only called if consent was actively opted in to.
   *
   * @return int Activity ID created.
   */
  public function recordConsent(int $contactID, array $data) :void {

    if (class_exists('CRM_Gdpr_CommunicationsPreferences_Utils')) {
      \CRM_Gdpr_CommunicationsPreferences_Utils::createCommsPrefActivity($contactID,
        ['activity_source' => "<p>Opted-in via Grassroots Petition "
        . htmlspecialchars($this->getPetitionTitle())
        . ' on page '
        . htmlspecialchars($data['location'])
        . '</p>'
        ]);
    }


    // todo extract this from the petition side of things; use a hook.
    // Add them to the P&P newsletter
    $groupID = 62; // xxx remove hard coded value!
    list($total, $added, $notAdded) = \CRM_Contact_BAO_GroupContact::addContactsToGroup([$contactID], $groupID, 'Web', 'Added');

    // Add them to the email consent group
    $emailConsentGroup = \Civi\Api4\Group::get(FALSE)
        ->addSelect('id')
        ->addWhere('name', '=', 'consent_all_email')
        ->execute()
        ->first()['id'] ?? NULL;
    if (!$emailConsentGroup) {
      Civi::log()->error("Failed to find consent_all_email Group; was going to add contact $contactID into it as they signed up.");
    }
    else {
      list($total, $added, $notAdded) = \CRM_Contact_BAO_GroupContact::addContactsToGroup([$contactID], $emailConsentGroup, 'Web', 'Added');
    }

    if (!empty($data['phone'])) {
      // Add them to the phone consent group, if phone number given
      $phoneConsentGroup = \Civi\Api4\Group::get(FALSE)
          ->addSelect('id')
          ->addWhere('name', '=', 'consent_all_phone')
          ->execute()
          ->first()['id'] ?? NULL;
      if (!$phoneConsentGroup) {
        Civi::log()->error("Failed to find consent_all_phone Group; was going to add contact $contactID into it as they signed up.");
      }
      else {
        list($total, $added, $notAdded) = \CRM_Contact_BAO_GroupContact::addContactsToGroup([$contactID], $phoneConsentGroup, 'Web', 'Added');
      }
    }
    // Add them to the group for this petition

  }
  /**
   * Send the thank you email to the person who signed up.
   *
   * @param int $contactID
   * @param array $data
   *    The validated input data.
   */
  public function sendThankYouEmail(int $contactID, array $data, int $thanksMsgTplID) {

    $from = civicrm_api3('OptionValue', 'getvalue', [ 'return' => "label", 'option_group_id' => "from_email_address", 'is_default' => 1]);

    // We use the email send in the data, as that's what they'd expect.
    $params = [
      'id'             => $thanksMsgTplID,
      'from'           => $from,
      'to_email'       => $data['email'],
      // 'bcc'            => "forums@artfulrobot.uk",
      'contact_id'     => $contactID,
      'disable_smarty' => 1,
      /*
      'template_params' =>
      [ 'foo' => 'hello',
      // {$foo} in templates 'bar' => '123',
      // {$bar} in templates ],
      */
      ];

    try {
      civicrm_api3('MessageTemplate', 'send', $params);
    }
    catch (\Exception $e) {
      // Log silently.
      Civi::log()->error("Failed to send MessageTemplate with params: " . json_encode($params, JSON_PRETTY_PRINT) . " Caught " . get_class($e) . ": " . $e->getMessage());
    }
  }
  /**
   */
  public function syncImages() {
    if ($this->getCaseStatus() === 'grpet_Pending') {
      $this->deactivateImages();
    }
    else {
      // Case is public
      $this->activateImages();
    }
  }
  /**
   */
  public function activateImages() {
    // Main case image.

    // Get open case activity.
    $openCase = civicrm_api3('Activity', 'get', [
      'case_id' => $this->case['id'],
      'activity_type_id' => static::$activityTypesByName['Grassroots Petition created']['value'],
      'return' => ['id', 'status_id']
    ]);
    if (empty($openCase['id'])) {
      // This is an error!
      throw new \RuntimeException("Case {$this->case['id']} has no Grassroots Petition created activity.");
    }

    // Get first attachment for this activity.
    $attachment = civicrm_api3('Attachment', 'get', [
      'entity_table' => 'civicrm_activity',
      'entity_id' => $openCase['id'],
      'options' => ['limit' => 1, 'sort' => 'id'],
    ]);

    $filePath = $this->getFile('path');

    if ($attachment['count'] == 0) {
      // No main image.
      if (file_exists($filePath)) {
        unlink($filePath);
        Civi::log()->info("GrassrootsPetition: Deleted file '$filePath' since Case {$this->case['id']} no longer has an image file attached.");
      }
      return;
    }

    if (!file_exists($filePath)) {
      // File does not exist.
      $tempFile = $this->createPublicImage($attachment['values'][$attachment['id']]);
      if ($tempFile) {
        rename($tempFile, $filePath);
        Civi::log()->info("GrassrootsPetition: Created file '$filePath' for Case {$this->case['id']}");
      }
    }
  }
  /**
   * Returns absolute file path or url to an image.
   *
   * A URL is only returned if the file exists, however the path is always returned.
   *
   * If $activityID is 0 then a temp file name is returned.
   */
  public function getFile(string $pathOrUrl, ?int $activityID=NULL) :?string {
    $petitionHash = substr(sha1(CIVICRM_SITE_KEY . $this->case['id']), 0, 8);

    if ($activityID === 0) {
      if ($pathOrUrl !== 'path') {
        throw new \RuntimeException("requires 'path' if temp file requested");
      }
      $unique = substr(sha1(CIVICRM_SITE_KEY . 'grpe tempfile' ), 0, 8);
      $fileName = "$petitionHash-temp-$unique.jpg";
    }
    elseif ($activityID !== NULL) {
       $fileName = "$petitionHash-update-$activityID.jpg";
    }
    else {
      $fileName = "$petitionHash-main.jpg";
    }

    $filePath = Civi::paths()->getPath("[civicrm.files]/grassrootspetition-images/$fileName");
    if (!file_exists($filePath) && $pathOrUrl === 'url') {
      // URL requested, but file does not exist.
      return NULL;
    }

    $method = ['path' => 'getPath', 'url' => 'getUrl'][$pathOrUrl] ?? '';
    if (!$method) {
      // Coding error.
      throw new \Exception(__FUNCTION__ . " requires pathOrUrl to be path|url. '$pathOrUrl' given.");
    }
    return Civi::paths()->$method("[civicrm.files]/grassrootspetition-images/$fileName");
  }
  /**
   * Creates a temporary rescaled image file and returns its path, if successful.
   */
  public function createPublicImage(array $attachment) :?string {
    $attachmentID = ((int) $attachment['id'] ?? 0) ?: '<missing ID!>';
    if (!in_array(($attachment['mime_type'] ?? ''), ['image/jpeg'])) {
      // File is not of correct type.
      throw new \RuntimeException("Attachment $attachmentID is not image/jpeg");
    }
    if (!preg_match('/\.jpe?g$/', $attachment['name'] ?? '')) {
      // File is not of correct extension.
      throw new \RuntimeException("Attachment $attachmentID does not have jpeg/jpg extension.");
    }
    $src = $attachment['path'] ?? '';
    if (!$src || !file_exists($src) || !is_readable($src)) {
      throw new \RuntimeException("Attachment $attachmentID file $src is unreadable/non-existent.");
    }
    // Limit processing to 12MB files. Seems reasonable.
    if (filesize($src) > 1024 * 1024 * 12) {
      throw new \RuntimeException("Attachment $attachmentID does $src too big to process.");
    }
    // OK, we have something that could be a JPEG.
    if (!extension_loaded('gd') || !function_exists('gd_info')) {
      throw new \RuntimeException("Attachment $attachmentID can’t be processed as gd is not available.");
    }

    $tempFile = $this->getFile('path', 0);
    $imgProperties = getimagesize($src);
    if($imgProperties[2] !== IMAGETYPE_JPEG) {
      throw new \RuntimeException("Attachment $attachmentID file $src is not a jpeg file according to GD");
    }
    $srcImage = imagecreatefromjpeg($src);
    // Calculate new size.
    // We need images that are 1000px wide.
    $newW = 1000;
    $newH = $imgProperties[1] * $newW / $imgProperties[0];
    $offsetY = 0;
    $ratio = 9/16; // height/width
    if ($newH > ($ratio*$newW)) {
      // Image is taller than 16:9 ratio.
      // We will take a crop from the middle.
      $offsetY = (int) (($newH - $ratio*$newW)/2);
    }
    $destImg = imagecreatetruecolor($newW, $newH);
    imagecopyresampled($destImg, $srcImage, 0, 0,
      0, $offsetY,
      $newW, $newH,
      $imgProperties[0], $imgProperties[1]);
    // Save file.
    imagejpeg($destImg, $tempFile);
    // move_uploaded_file($image, $pathToImages.$imageName);
    return $tempFile;
  }

  function image_resize($source,$width,$height) {
    $new_width =150;
    $new_height =150;
    $thumbImg=imagecreatetruecolor($new_width,$new_height);
    return $thumbImg;
  }


  /**
   * Assert that the case is loaded; used by public getters.
   */
  protected function mustBeLoaded() {
    if (empty($this->case)) {
      throw new \RuntimeException("CaseWrapper: no case loaded.");
    }
  }
}
