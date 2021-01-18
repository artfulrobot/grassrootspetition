<?php
use Symfony\Component\Console\Helper\ProgressBar;


use CRM_Grassrootspetition_ExtensionUtil as E;
use Civi\Api4\GrassrootsPetitionCampaign;
use Civi\GrassrootsPetition\CaseWrapper;

/*
 * Run this with: cv scr removeAllData.php
 */
if (php_sapi_name() !== 'cli') {
  http_response_code(404);
  exit;
}

class Importer {

  /**
   * @see https://docs.google.com/spreadsheets/d/1T1IOQ0OipfoCN-ng-DNMxHpKubUALvhHrGXsXpjf5_A/edit#gid=1906811397
   */
  public $toImport = [
    ['Fossil Free: divestment', 'Stop Barclays Funding Fossil Fuels', 'https://act.peopleandplanet.org/petitions/barclays-bank-boycott-barclays?source=homepage&utm_medium=promotion&utm_source=homepage', 'YES'],
    ['Fossil Free: divestment', 'LSBU: Divest From Fossil Fuels', 'https://act.peopleandplanet.org/petitions/lsbu-divest-from-fossil-fuels', 'NO'],
    ['Fossil Free: divestment', 'End Barclays Sponsorship: Divest Pride', 'https://act.peopleandplanet.org/petitions/end-barclays-sponsorship-divest-pride', 'NO'],
    ['Fossil Free: divestment', 'Barclays: No More Oil, Invest in a Greener Future', 'https://act.peopleandplanet.org/petitions/barclays-no-more-oil-invest-in-a-greener-future', 'NO'],
    ['Fossil Free: divestment', 'End Fossil Fuel Recruitment Events - University of Oxford', 'https://act.peopleandplanet.org/petitions/end-fossil-fuel-recruitment-events-university-of-oxford', 'NO'],
    ['Fossil Free: divestment', 'Boycott Barclays across Portsmouth', 'https://act.peopleandplanet.org/petitions/boycott-barclays-across-portsmouth', 'NO'],
    ['Fossil Free: divestment', 'Cambridge University, cut your ties with fossil fuel money and commit to divestment now.', 'https://act.peopleandplanet.org/petitions/cambridge-university-cut-your-ties-with-fossil-fuel-money-and-commit-to-divestment-now', 'YES'],
    ['Fossil Free: divestment', 'Cambridge University, listen to your members and divest from fossil fuels', 'https://act.peopleandplanet.org/petitions/cambridge-university-listen-to-your-members-and-divest-from-fossil-fuels', 'YES'],
    ['Fossil Free: divestment', 'Loughborough University Boycott Barclays', 'https://act.peopleandplanet.org/petitions/loughborough-university-boycott-barclays', 'NO'],
    ['Fossil Free: divestment', 'University of Bath Boycott Barclays', 'https://act.peopleandplanet.org/petitions/university-of-bath-boycott-barclays', 'NO'],
    ['Fossil Free: divestment', 'University of Glasgow Boycott Barclays', 'https://act.peopleandplanet.org/petitions/university-of-glasgow-boycott-barclays', 'NO'],
    ['Fossil Free: divestment', 'University of Cambridge Boycott Barclays', 'https://act.peopleandplanet.org/petitions/university-of-cambridge-boycott-barclays', 'NO'],
    ['Fossil Free: divestment', 'University of Leeds Boycott Barclays', 'https://act.peopleandplanet.org/petitions/university-of-leeds-boycott-barclays', 'NO'],
    ['Fossil Free: divestment', 'University of Oxford Boycott Barclays', 'https://act.peopleandplanet.org/petitions/university-of-oxford-boycott-barclays', 'NO'],
    ['Fossil Free: divestment', 'UEA Boycott Barclays', 'https://act.peopleandplanet.org/petitions/uea-boycott-barclays', 'NO'],
    ['Fossil Free: divestment', 'University of Sheffield Boycott Barclays', 'https://act.peopleandplanet.org/petitions/university-of-sheffield-boycott-barclays', 'NO'],
    ['Fossil Free: divestment', 'Blackpool and Fylde College Boycott Barclays', 'https://act.peopleandplanet.org/petitions/blackpool-and-fylde-college-boycott-barclays', 'NO'],
    ['Fossil Free: divestment', 'UCL Boycott Barclays', 'https://act.peopleandplanet.org/petitions/ucl-boycott-barclays', 'NO'],
    ['Fossil Free: divestment', 'Bristol University to Boycott Barclays', 'https://act.peopleandplanet.org/petitions/bristol-university-to-boycott-barclays', 'NO'],
    ['Fossil Free: divestment', 'UWTSD, Boycott Barclays!', 'https://act.peopleandplanet.org/petitions/uwtsd-boycott-barclays', 'NO'],
    ['Fossil Free: divestment', 'University of Edinburgh Boycott Barclays', 'https://act.peopleandplanet.org/petitions/university-of-edinburgh-boycott-barclays', 'NO'],
    ['Fossil Free: divestment', 'Hallé Concerts Society Boycott Barclays', 'https://act.peopleandplanet.org/petitions/halle-concerts-society-boycott-barclays', 'NO'],

    ['Migration', 'Legal Recognition for Climate Refugees', 'https://act.peopleandplanet.org/petitions/legal-recognition-for-climate-refugees', 'YES'],
    ['Migration', 'Stop the Media Scapegoating ‘Illegal’ Migrants', 'https://act.peopleandplanet.org/petitions/mainstream-papers-stop-calling-migrants-illegal', 'YES'],
    ['Migration', 'Bristol Uni to break ties with the Border Industry', 'https://act.peopleandplanet.org/petitions/bristol-uni-to-break-ties-with-the-border-industry', 'YES'],

    ['Workers Rights', 'North Eastern Universities go Sweatshop Free', 'https://act.peopleandplanet.org/petitions/calling-on-north-eastern-universities-to-go-sweatshop-free'],
    ['Workers Rights', 'King’s College London Go Sweatshop Free - Join Electronics Watch', 'https://act.peopleandplanet.org/petitions/king-s-college-london-go-sweatshop-free-join-electronics-watch'],
    ['Workers Rights', 'Plymouth University - Help us stop Sweatshops; sign up to Electronics Watch', 'https://act.peopleandplanet.org/petitions/plymouth-university-help-us-stop-sweatshops-sign-up-to-electronics-watch'],
    ['Workers Rights', 'Make Surrey’s Tech Ethical', 'https://act.peopleandplanet.org/petitions/make-surrey-s-tech-ethical'],
    ['Workers Rights', 'University of Wales, Trinity St. David Go Sweatshop Free - Join Electronics Watch', 'https://act.peopleandplanet.org/petitions/university-of-wales-trinity-st-david-go-sweatshop-free-join-electronics-watch'],
    ['Workers Rights', 'Stop Union Busting in the Electronics Industry', 'https://act.peopleandplanet.org/petitions/stop-union-busting-in-the-electronics-industry'],
  ];

  /**
   */
  public static $campaigns;

  /**
   */
  public static $effortsToCampaignID = [];

  public $efforts = [];

  public function __construct() {
    static::$campaigns = GrassrootsPetitionCampaign::get(FALSE)
      ->execute()
      ->indexBy('name')
      ->getArrayCopy();
  }

  public function go() {

    // Import 'efforts' as campaigns
    $this->importEfforts();

    foreach ($this->toImport as $petitionToImport) {
      $campaignName = $petitionToImport[0];
      $petitionTitle = $petitionToImport[1];
      $oldUrl = $petitionToImport[2];
      $active = $petitionToImport[3] ?? 'YES';
      $slug = preg_replace('@^https.*\.org/petitions/([^/?#]+).*$@', '$1', $oldUrl);

      $this->log("Doing $slug ($campaignName: $petitionTitle)");

      // Find the petition
      $petition = CRM_Core_DAO::executeQuery(
        "SELECT p.*, u.first_name, u.last_name, u.email, l.venue, t.name targetName
        FROM csl.petitions p
        INNER JOIN csl.users u ON p.user_id = u.id
        INNER JOIN csl.locations l on p.location_id = l.id
        INNER JOIN csl.targets t on p.target_id = t.id
        WHERE p.slug = %1", [1 => [$slug, 'String']]);
      if (!$petition->fetch()) {
        $this->log("xxx CSL slug not found: '$slug'");
      }
      else {
        $this->log("CSL slug found: '$slug'");
        $campaign = $this->ensureCampaign((int) $petition->effort_id);
        $this->log("CSL campaign $campaign[id]");
      }

      // We need to find the petition owner.
      $xcmParams = [
        'contact_type' => 'Individual',
        'email'        => $petition->email,
        'first_name'   => $petition->first_name,
        'last_name'    => $petition->last_name,
      ];
      $contactID = (int) civicrm_api3('Contact', 'getorcreate', $xcmParams)['id'];

      $petitionCase = CaseWrapper::createNew(
        $contactID,
        $petition->title,
        $campaign['label'],
        $petition->venue, // location
        $petition->targetName,
        $petition->who,
        $slug
      );
      $this->log("Created petition {$petitionCase->getID()} from $slug");

      // Add other details to petition.
      $petitionCase->setCustomData([
        'grpet_why' => $petition->why,
        'grpet_what' => $petition->what,
      ]);
      $petitionCase->setStatus('Open');

      $this->importImage($petition, $petitionCase);
      $this->migrateSignatures((int) $petition->id, $petitionCase);
      break;
    }
  }

  public function importEfforts() {
    $efforts = CRM_Core_DAO::executeQuery('SELECT * FROM csl.efforts WHERE title_default <> ""');
    while ($efforts->fetch()) {
      $this->ensureCampaign((int) ($efforts->id), $efforts);
    }
  }
  public function importImage(CRM_Core_DAO $petition, CaseWrapper $petitionCase) {
    if (!$petition->image_file_name && $petition->image_file_size > 10240) {
      return;
    }
    // image_file_name:                                                        Sheffield-Barclays-Boycott-640x400.jpg
    // https://d8s293fyljwh4.cloudfront.net/petitions/images/202824/horizontal/Sheffield-Barclays-Boycott-640x400.jpg?1504786097
    //                                                       petID
    // https://d8s293fyljwh4.cloudfront.net/petitions/images/202824/original/Sheffield-Barclays-Boycott-640x400.jpg
    // OK try to load image.
    $imageUrl = "https://d8s293fyljwh4.cloudfront.net/petitions/images/$petition->id/original/$petition->image_file_name";

    $imageContent = file_get_contents($imageUrl);
    if (!$imageContent) {
      $this->log("Failed to load image $imageUrl");
      return;
    }
    // Got image.

    // The image is stored on the grpet creted activity.
    $activity = $petitionCase->getPetitionCreatedActivity();

    // Create an attachment for a core field
    $result = civicrm_api3('Attachment', 'create', array(
      'entity_table' => 'civicrm_activity',
      'entity_id'    => $activity['id'],
      'name'         => $petition->image_file_name,
      'mime_type'    => 'image/jpeg',
      'content'      => $imageContent,
    ));
    $attachment = $result['values'][$result['id']];
    $this->log("Imported image $petition->image_file_name as '$attachment[name]': " . $attachment['url']  . "\n");
  }
  /**
   * Import 'efforts' as 'campaigns'
   */
  public function ensureCampaign(int $effortID, ?CRM_Core_DAO $effort=NULL) :array {
    if (!isset(static::$effortsToCampaignID[$effortID])) {
      $this->log("Failed to find effort $effortID");

      if (empty($effort)) {
        throw new \RuntimeException("can't lookup effort $effortID without original data");
      }

      // Missing campaign
      $title = trim(preg_replace('/{{target.name}} /', '', $effort->title_default));

      $campaign = static::$campaigns[$title] ?? NULL;
      if (!$campaign) {
        $this->log("Failed to find campaign '$title'");
        // Not found, create now.
        $campaign = GrassrootsPetitionCampaign::create(FALSE)
          ->addValue('name', $title)
          ->addValue('label', $title)
          ->addValue('template_what', $effort->what_default)
          ->addValue('template_why', $effort->why_default)
          ->addValue('is_active', 1)
          ->execute()->first();
        static::$campaigns[$title] = $campaign;
      }
      else {
        $this->log("Found campaign $campaign[id] for '$title'");
      }
      static::$effortsToCampaignID[$effortID] = $campaign;
    }
    return static::$effortsToCampaignID[$effortID];
  }

  public function migrateSignatures(int $petitionID, CaseWrapper $petitionCase) {
    // We need to create one activity per signer, but we need to make sure it's not already imported.

    // 1. failsafe: count all exising webhook signatures.

    // This will be really slow.
    $total = CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM csl.signatures WHERE petition_id = $petitionID");

    // Import: people should be in db already, so just use name, email.
    // - comments. Can't import these yet. xx todo add structure.
    $sigs = CRM_Core_DAO::executeQuery("SELECT email, first_name, last_name, s.created_at, source, c.text
      FROM csl.signatures s
      LEFT JOIN csl.comments c ON c.signature_id = s.id AND c.approved = 't'
      WHERE petition_id = $petitionID");

    $done = 0;
    $start = time();

    $signedActivityTypeID = (int) static::$activityTypesByName['Grassroots Petition signed']['value'];
    while ($sigs->fetch()) {

      // Find contact.
      $contactID = (int) civicrm_api3('Contact', 'getorcreate', [
        'first_name' => $sigs->first_name,
        'last_name'  => $sigs->last_name,
        'email'      => $sigs->email,
      ])['id'];

      $petitionCase->addSignedPetitionActivity($contactID, [
        'location' => $sigs->source,
// xxx
      ]);

      $done++;
      if ($done % 100) {
        $rate = (time() - $start) / $done;
        $est = ($total - $done) * rate;
        $h = [];
        if ($est > 60*60) {
          $_ = floor($est/60/60);
          $h[] = $_ . " hours";
          $est -= $_;
        }
        if ($est > 60) {
          $_ = floor($est/60);
          $h[] = $_ . " mins";
          $est -= $_;
        }
        if ($est > 0) {
          $h[] = ((int) $est) . "s";
        }
        $h = implode(', ', $h);
        print "Done $done/$todo (" . round($done*100/$todo, 1) . '%) Est ' . $h . " to go. (Rate: " . round($rate,3) . "s per record)\n";
      }
    }



  }
  public function log($msg, $obj='object not provided') {
    print $msg . "\n"
      . (
        ($obj === 'object not provided')
        ? ''
        : (json_encode($obj, JSON_PRETTY_PRINT) . "\n"));
  }
}

$i = new Importer();
$i->go();
