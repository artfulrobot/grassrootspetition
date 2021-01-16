<?php

use CRM_Grassrootspetition_ExtensionUtil as E;
use Civi\Api4\GrassrootsPetitionCampaign;

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

  public static $campaigns;
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
    return;


    foreach ($this->toImport as $petitionToImport) {
      $campaignName = $petitionToImport[0];
      $petitionTitle = $petitionToImport[1];
      $oldUrl = $petitionToImport[2];
      $active = $petitionToImport[3] ?? 'YES';
      $slug = preg_replace('@^https.*\.org/([^/?#]+).*$@', '$1', $oldUrl);

      $log->info("Doing $slug ($campaignName: $petitionTitle)");
      $campaignID = $this->ensureCampaign($campaignName);
      $petitionID = $this->migratePetitionDefinition($slug, $campaignID, $active !== 'NO');
      $log->info("Created petition $petitionID from $slug");
      $this->migrateSignatures($petitionID);
      break;
    }
  }

  public function importEfforts() {
    $efforts = CRM_Core_DAO::executeQuery('SELECT * FROM csl.efforts');
    while ($efforts->fetch()) {
      $title = trim(preg_replace('/{{target.name}} /', '', $efforts->title_default));
      if (!$title) {
        continue;
      }
      $this->log("Looking up effort called: $title");
      $this->efforts[(int) $efforts->id] = $title;
    }
    $this->log("efforts", $this->efforts);
    // @todo
  }
  public function ensureCampaign($campaignName) :int {
    if (!isset(static::$campaigns[$campaignName])) {
      // Missing campaign, create it now.
      $campaign = GrassrootsPetitionCampaign::create(FALSE)
        ->addValue('name', $campaignName)
        ->addValue('label', $campaignName)
        ->addValue('is_active', 1)
        ->execute()->first();
      static::$campaigns[$campaignName] = $campaign;
    }
    return (int) static::$campaigns[$campaignName]['id'];
  }

  public function migratePetitionDefinition(string $slug, int $campaignID, bool $isActive) :int {
    // Load petition.

  }
  public function migrateSignatures(int $petitionID) {

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
