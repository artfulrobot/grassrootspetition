(function(angular, $, _) {

  angular.module('grassrootspetition').config(function($routeProvider) {
      $routeProvider.when('/grassrootspetition/campaigns', {
        controller: 'PetitionInlayCampaigns',
        controllerAs: '$ctrl',
        templateUrl: '~/grassrootspetition/Campaigns.html',

        // If you need to look up data when opening the page, list it out
        // under "resolve".
        resolve: {
          various: function($route, crmApi4) {
            const params = {
              campaigns: ['GrassrootsPetitionCampaign', 'get', {
                withStats: true,
                orderBy: {is_active: 'DESC', name: 'ASC'}
              }],
              messageTpls: [ 'MessageTemplate', 'get', {
                select: ["id", "msg_title", "msg_subject"],
                where: [
                  ["is_active", "=", true], ["is_sms", "=", false],
                  ["workflow_id", "IS NULL"]
                ],
                orderBy: {msg_title: 'ASC'}},
                'id']
            };
            return crmApi4(params);
          },
        }
      });
    }
  );

  // The controller uses *injection*. This default injects a few things:
  //   $scope -- This is the set of variables shared between JS and HTML.
  angular.module('grassrootspetition').controller('PetitionInlayCampaigns', function($scope, crmApi4, crmStatus, crmUiHelp, various) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('grassrootspetition');
    // var hs = $scope.hs = crmUiHelp({file: 'CRM/grassrootspetition/InlaySignupA'}); // See: templates/CRM/inlaysignup/InlaySignupA.hlp
    // Local variable for this controller (needed when inside a callback fn where `this` is not available).
    var ctrl = this;

    console.log(various.campaigns);
    $scope.campaigns = various.campaigns;
    $scope.stage = 'listCampaigns';
    $scope.campaignBeingEdited = null;
    $scope.CRM = CRM;
    $scope.messageTpls = various.messageTpls;
    $scope.templateImageFile = null;

    const emptyCampaign = {
          id: 0,
          name: '',
          label: '',
          description : '',
          is_active : 0,
          template_what : '',
          template_why : '',
          template_title : '',
          template_tweet : '',
          template_image_alt : '',
          slug : '',
          notify_contact_id:'',
          notify_email:'',
          thanks_msg_template_id: null,
          confirm_msg_template_id: null,
          allow_mailings: 'default',
        };
    $scope.editCampaign = function(campaign) {
      if (campaign) {
        $scope.campaignBeingEdited = Object.assign({}, emptyCampaign, campaign);
        // Swap out the download_permissions
        $scope.campaignBeingEdited.downloadPermissions = {
          override: ($scope.campaignBeingEdited.download_permissions || []).includes('override'),
          email: ($scope.campaignBeingEdited.download_permissions || []).includes('email'),
          name: ($scope.campaignBeingEdited.download_permissions || []).includes('name'),
        };
        $scope.campaignBeingEdited.is_active = campaign.is_active ? '1' : '0';
        console.log($scope.campaignBeingEdited);
      }
      else {
        // New campaign
        $scope.campaignBeingEdited = Object.assign({}, emptyCampaign);
      }
      $scope.stage = 'editCampaign';
    };
    $scope.viewPetitions = function(campaign) {
      // New campaign
      $scope.selectedCampaign = campaign;
      $scope.stage = 'viewPetitions';
    };

    $scope.save = function() {

      const records = Object.assign({}, $scope.campaignBeingEdited);
      if (records.downloadPermissions.override) {
        const downloadPermissions = ['override'];
        ['name', 'email'].forEach(f => {
          if (records.downloadPermissions[f]) {
            downloadPermissions.push(f);
          }
        });
        records.download_permissions = downloadPermissions;
      }
      else {
        records.download_permissions = null;
      }
      delete records.downloadPermissions;
      delete records['$$hashKey'];
      delete records.stats;

      const params = {
        saved: ['GrassrootsPetitionCampaign', 'save', {records: [records]}],
        campaigns: ['GrassrootsPetitionCampaign', 'get', {
          orderBy: {is_active: 'DESC', name: 'ASC'}
        }],
      };

      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Saving...'), success: ts('Saved')},
        // The save action. Note that crmApi() returns a promise.
        crmApi4(params)
      ).then(r => {
        console.log("save result", r);
        $scope.campaigns = r.campaigns;
        $scope.stage = 'listCampaigns';
        $scope.campaignBeingEdited = null;
      });
    };
    $scope.impersonate = function(petition) {

      if (petition.impersonateLink) {
        // We already have one, reuse it.
        window.open(petition.impersonateLink);
        return;
      }
      const params = {
        link: ['GrassrootsPetition', 'MakeAuthLink', { id: petition.caseID }],
      };
      crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Generating link...'), success: ts('Done')},
        // The save action. Note that crmApi() returns a promise.
        crmApi4(params)
      ).then(r => {
        console.log("MakeAuthLink result", r);
        petition.impersonateLink = r.link.link;
        window.open(petition.impersonateLink);
      });

    };

    $scope.uploadNewImage = function (e) {
      e.preventDefault();
      const filesInput = document.getElementById('campaignImageFileUploadInput');
      if (filesInput.files.length !== 1) {
        alert("You need to select a file first.");
        return;
      }
      console.log({filesInput});

      d = {
        campaignID: $scope.campaignBeingEdited.id,
        fileName: filesInput.files[0].name,
        data: '',
      };

      var p = new Promise((resolve, reject) => {
        if (filesInput.files.length === 1) {
          var fr = new FileReader();
          fr.addEventListener('load', e => {
            // File loaded.
            console.log("File read ok");
            d.data = fr.result;
            resolve(d);
          });
          fr.readAsDataURL(filesInput.files[0]);
        }
      });
      p.then((d) => {
        return crmStatus(
          // Status messages. For defaults, just use "{}"
          {start: ts('Uploading...'), success: ts('Done')},
          // The save action. Note that crmApi() returns a promise.
          crmApi4('GrassrootsPetitionCampaign', 'uploadImage', d)
        ).then(r => {
          console.log("upload result", r);
          $scope.campaignBeingEdited.template_image_url = r.image_url;
          $scope.campaigns.find(c => c.id === $scope.campaignBeingEdited.id).template_image_url = r.image_url;
        });
      });
    };

  });

})(angular, CRM.$, CRM._);
