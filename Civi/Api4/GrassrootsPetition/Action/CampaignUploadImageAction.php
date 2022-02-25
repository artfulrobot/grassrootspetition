<?php
namespace Civi\Api4\GrassrootsPetition\Action;

use Civi\Api4\Generic\DAOGetAction;
use Civi\Api4\Generic\Result;
use \API_Exception;
use Civi\GrassrootsPetition\CaseWrapper;
use CRM_Core_DAO;
use Civi;

class CampaignUploadImageAction extends DAOGetAction {

  /**
   * Grassroots Petition Campaign ID
   *
   * @var int
   */
  protected $campaignID;

  /**
   * File name
   *
   * @description Nb. we only care about the extension bit, the rest is discarded.
   *
   * @var string
   */
  protected $fileName;

  /**
   * Raw file data
   *
   * @var string
   */
  protected $data;


  /**
   *
   */
  public function _run(Result $result) {

    // Check the image is what we want.
    if (!preg_match('/\.(jpe?g|png)$/', $this->fileName, $matches)) {
      throw new API_Exception('Image must be jpeg/png');
    }

    $asset = \Civi\Inlay\Asset::singleton();
    $identifier = 'grassrootspetition_campaign_' . $this->campaignID . '_default_image';
    $extension = $matches[1];

    if (preg_match('@^data:(image/(?:jpeg|png));base64(.*)$@', $this->data ?? '', $m)) {
      // An image was sent.
      $this->data = '';
      $imageData = base64_decode($m[2]);
      // $imageFileType = $m[1];
      unset($m);
      if ($imageData) {
        $asset->saveAssetFromData($identifier, $extension, $imageData);
        $result['image_url'] = $asset->getAssetUrl($identifier);
      }
    }
    else {
      throw new API_Exception('Image must be jpeg/png');
    }

    // @todo resize images that are too small.
  }
}


