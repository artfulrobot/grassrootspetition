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

    const emptyCampaign = {
          id: 0,
          name: '',
          label: '',
          description : '',
          is_active : 0,
          template_what : '',
          template_why : '',
          template_title : '',
          slug : '',
          notify_contact_id:'',
          notify_email:'',
          thanks_msg_template_id: null,
          confirm_msg_template_id: null,
        };
    $scope.editCampaign = function(campaign) {
      if (campaign) {
        $scope.campaignBeingEdited = Object.assign({}, emptyCampaign, campaign);
        $scope.campaignBeingEdited.is_active = campaign.is_active ? '1' : '0';
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

      const params = {
        saved: ['GrassrootsPetitionCampaign', 'save', {
          records: [$scope.campaignBeingEdited]
        }],
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
  });

})(angular, CRM.$, CRM._);
