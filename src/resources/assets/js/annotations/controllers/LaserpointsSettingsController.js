/**
 * @namespace biigle.annotations
 * @ngdoc controller
 * @name LaserpointsSettingsController
 * @memberOf biigle.annotations
 * @description Controller for the laserpoints settings
 */
angular.module('biigle.annotations').controller('LaserpointsSettingsController', function ($scope, laserpoints, settings) {
        "use strict";

        var key = 'laserpoint_opacity';

        settings.setDefaultSettings(key, '1');

        $scope[key] = settings.getPermanentSettings(key);

        var pointsShown = $scope[key] != '0';

        $scope.$watch(key, function (opacity) {
            settings.setPermanentSettings(key, opacity);
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
        if (pointsShown) {
            laserpoints.show($scope);
        }


    }
);
