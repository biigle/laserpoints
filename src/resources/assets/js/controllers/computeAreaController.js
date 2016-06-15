/**
 * @namespace dias.transects
 * @ngdoc controller
 * @name FilterController
 * @memberOf dias.transects
 * @description Controller for the filter feature of the transects page
 */
angular.module('dias.laserpoints').controller('computeAreaController', function ($scope,$http,URL,msg) {
        "use strict";
        $scope.distance = null;
        $scope.iscomputing = false;
        $scope.request=function(imageid,distance){
            if (distance === undefined){
                distance = $scope.distance;
            }
            $scope.iscomputing=true;
            $http({
                method: 'POST',
                url: URL+"/api/v1/images/"+imageid+"/laserpoints/area/"+distance
            }).then(function successCallback(response) {
                    msg.success("The area will be computed soon.");
            }, function errorCallback(response) {
                    msg.danger("An error occured. If you keep getting this error please contact the administrator.");
                    $scope.iscomputing=false;
            });
        };
    }
);
