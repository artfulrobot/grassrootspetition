(function(angular, $, _) {

  angular.module('grassrootspetition').config(function($routeProvider) {
      $routeProvider.when('/inlays/grassrootspetition/:id', {
        controller: 'PetitionInlay',
        controllerAs: '$ctrl',
        templateUrl: '~/grassrootspetition/PetitionInlay.html',

        // If you need to look up data when opening the page, list it out
        // under "resolve".
        resolve: {
          various: function($route, crmApi4, $route) {
            const params = {
              inlayTypes: ['InlayType', 'get', {}, 'class'],
            };
            if ($route.current.params.id > 0) {
              params.inlay = ['Inlay', 'get', {where: [["id", "=", $route.current.params.id]]}, 0];
            }
            return crmApi4(params);
          },
        }
      });
    }
  );

  // The controller uses *injection*. This default injects a few things:
  //   $scope -- This is the set of variables shared between JS and HTML.
  angular.module('grassrootspetition').controller('PetitionInlay', function($scope, crmApi4, crmStatus, crmUiHelp, various) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('grassrootspetition');
    // var hs = $scope.hs = crmUiHelp({file: 'CRM/grassrootspetition/InlaySignupA'}); // See: templates/CRM/inlaysignup/InlaySignupA.hlp
    // Local variable for this controller (needed when inside a callback fn where `this` is not available).
    var ctrl = this;

    $scope.mailingGroups = various.groups;
    $scope.inlayType = various.inlayTypes['Civi\\Inlay\\GrassrootsPetition'];
    console.log({various}, $scope.inlayType);
    $scope.mailingGroups = various.groups;
    $scope.messageTpls = various.messageTpls;

    if (various.inlay) {
      $scope.inlay = various.inlay;
    }
    else {
      $scope.inlay = {
        'class' : 'Civi\\Inlay\\GrassrootsPetition',
        name: 'New ' + $scope.inlayType.name,
        public_id: 'new',
        id: 0,
        config: JSON.parse(JSON.stringify($scope.inlayType.defaultConfig)),
      };
    }
    const inlay = $scope.inlay;

    $scope.save = function() {

      console.log("Saving " + JSON.stringify(inlay));

      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Saving...'), success: ts('Saved')},
        // The save action. Note that crmApi() returns a promise.
        crmApi4('Inlay', 'save', { records: [inlay] })
      ).then(r => {
        console.log("save result", r);
        window.location = CRM.url('civicrm/a?#inlays');
      });
    };
  });

})(angular, CRM.$, CRM._);
