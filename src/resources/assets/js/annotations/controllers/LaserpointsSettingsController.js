/**
 * @namespace dias.annotations
 * @ngdoc controller
 * @name LaserpointsSettingsController
 * @memberOf dias.annotations
 * @description Controller for the laserpoints settings
 */
angular.module('dias.annotations').controller('LaserpointsSettingsController', function ($scope, laserpoints) {
        "use strict";

        $scope.setDefaultSettings('laserpoint_opacity', '1');

        var pointsShown = $scope.settings.laserpoint_opacity != '0';

        $scope.$watch('settings.laserpoint_opacity', function (opacity) {
            laserpoints.setOpacity(opacity);

            if (!pointsShown && opacity > 0) {
                laserpoints.show($scope);
                pointsShown = true;
            } else if (pointsShown && opacity <= 0) {
                laserpoints.hide();
                pointsShown = false;
            }
        });

        // immediately show mask if stored opacity is not 0
        if ($scope.settings.laserpoint_opacity != '0') {
            laserpoints.show($scope);
        }


    }
);
