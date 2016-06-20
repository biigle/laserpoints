/**
 * @namespace dias.transects
 * @ngdoc controller
 * @name FilterController
 * @memberOf dias.transects
 * @description Controller for the filter feature of the transects page
 */
angular.module('dias.transects').controller('computeAreaControllerTransects', function ($scope,$http,URL,msg) {
        "use strict";
        $scope.data = {
            distance: null,
            iscomputing:false
        };
        $scope.request=function(transectid){
            msg.success("The area of all images will be computed soon.");
            var distance = $scope.data.distance;
            $scope.data.iscomputing=true;
            $http({
                method: 'POST',
                url: URL+"/api/v1/transects/"+transectid+"/laserpoints/area/"+distance
            }).then(function successCallback(response) {
            }, function errorCallback(response) {
                    msg.danger("An error occured. If you keep getting this error please contact the administrator.");
                    $scope.data.iscomputing=false;
            });
        };
    }
);
