/**
 * @namespace dias.laserpoints
 * @ngdoc controller
 * @name LaserpointsController
 * @memberOf dias.laserpoints
 * @description Controller for the laserpoints panel
 */
angular.module('dias.laserpoints').controller('LaserpointsController', function ($scope,$http, URL, msg) {
        "use strict";

        var computing = false;
        var submitted = false;

        $scope.distance = null;

        var handleSuccess = function () {
            submitted = true;
        };

        var handleError = function (response) {
            computing = false;
            msg.responseError(response);
        };

        var detect = function (id, distance) {
            computing = true;
            $http({
                method: 'POST',
                url: URL + "/api/v1/images/" + id + "/laserpoints/area",
                data: {distance: distance}
            }).then(handleSuccess, handleError);
        };

        $scope.isComputing = function () {
            return computing;
        };

        $scope.isSubmitted = function () {
            return submitted;
        };

        $scope.newDetection = function (id) {
            if (!$scope.distance || $scope.distance <= 0) return;

            detect(id, $scope.distance);
        };

        $scope.reDetection = function (id, distance) {
            // take new $scope.distance or else the default distance
            distance = $scope.distance || distance;
            detect(id, distance);
        };
    }
);
