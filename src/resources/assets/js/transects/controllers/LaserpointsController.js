/**
 * @namespace biigle.transects
 * @ngdoc controller
 * @name LaserpointsController
 * @memberOf biigle.transects
 * @description Controller for the laserpoints popover
 */
angular.module('biigle.transects').controller('LaserpointsController', function ($scope,$http, URL, msg, TRANSECT_ID) {
        "use strict";

        var computing = false;
        var submitted = false;

        $scope.data = {
            distance: null
        };

        var handleSuccess = function () {
            submitted = true;
        };

        var handleError = function (response) {
            computing = false;
            msg.responseError(response);
        };

        $scope.isComputing = function () {
            return computing;
        };

        $scope.isSubmitted = function () {
            return submitted;
        };

        $scope.newDetection = function () {
            if (!$scope.data.distance || $scope.data.distance <= 0) return;

            computing = true;
            $http({
                method: 'POST',
                url: URL + "/api/v1/transects/" + TRANSECT_ID + "/laserpoints/area",
                data: {distance: $scope.data.distance}
            }).then(handleSuccess, handleError);
        };
    }
);
