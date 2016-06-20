/**
 * @namespace dias.transects
 * @description The DIAS transects module.
 */
angular.module('dias.laserpoints', ['dias.api', 'dias.ui']);

/*
 * Disable debug info in production for better performance.
 * see: https://code.angularjs.org/1.4.7/docs/guide/production
 */
angular.module('dias.laserpoints').config(["$compileProvider", function ($compileProvider) {
    "use strict";

    $compileProvider.debugInfoEnabled(false);
}]);

/**
 * @namespace dias.transects
 * @ngdoc controller
 * @name FilterController
 * @memberOf dias.transects
 * @description Controller for the filter feature of the transects page
 */
angular.module('dias.laserpoints').controller('computeAreaController', ["$scope", "$http", "URL", "msg", function ($scope,$http,URL,msg) {
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
    }]
);

/**
 * @namespace dias.transects
 * @ngdoc controller
 * @name FilterController
 * @memberOf dias.transects
 * @description Controller for the filter feature of the transects page
 */
angular.module('dias.transects').controller('computeAreaControllerTransects', ["$scope", "$http", "URL", "msg", function ($scope,$http,URL,msg) {
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
    }]
);

//# sourceMappingURL=data:application/json;base64,eyJ2ZXJzaW9uIjozLCJzb3VyY2VzIjpbIm1haW4uanMiLCJjb250cm9sbGVycy9jb21wdXRlQXJlYUNvbnRyb2xsZXIuanMiLCJjb250cm9sbGVycy9jb21wdXRlQXJlYUNvbnRyb2xsZXJUcmFuc2VjdHMuanMiXSwibmFtZXMiOltdLCJtYXBwaW5ncyI6IkFBQUE7Ozs7QUFJQSxRQUFBLE9BQUEsb0JBQUEsQ0FBQSxZQUFBOzs7Ozs7QUFNQSxRQUFBLE9BQUEsb0JBQUEsNEJBQUEsVUFBQSxrQkFBQTtJQUNBOztJQUVBLGlCQUFBLGlCQUFBOzs7Ozs7Ozs7O0FDTkEsUUFBQSxPQUFBLG9CQUFBLFdBQUEsMkRBQUEsVUFBQSxPQUFBLE1BQUEsSUFBQSxLQUFBO1FBQ0E7UUFDQSxPQUFBLFdBQUE7UUFDQSxPQUFBLGNBQUE7UUFDQSxPQUFBLFFBQUEsU0FBQSxRQUFBLFNBQUE7WUFDQSxJQUFBLGFBQUEsVUFBQTtnQkFDQSxXQUFBLE9BQUE7O1lBRUEsT0FBQSxZQUFBO1lBQ0EsTUFBQTtnQkFDQSxRQUFBO2dCQUNBLEtBQUEsSUFBQSxrQkFBQSxRQUFBLHFCQUFBO2VBQ0EsS0FBQSxTQUFBLGdCQUFBLFVBQUE7b0JBQ0EsSUFBQSxRQUFBO2VBQ0EsU0FBQSxjQUFBLFVBQUE7b0JBQ0EsSUFBQSxPQUFBO29CQUNBLE9BQUEsWUFBQTs7Ozs7Ozs7Ozs7OztBQ2hCQSxRQUFBLE9BQUEsa0JBQUEsV0FBQSxvRUFBQSxVQUFBLE9BQUEsTUFBQSxJQUFBLEtBQUE7UUFDQTtRQUNBLE9BQUEsT0FBQTtZQUNBLFVBQUE7WUFDQSxZQUFBOztRQUVBLE9BQUEsUUFBQSxTQUFBLFdBQUE7WUFDQSxJQUFBLFFBQUE7WUFDQSxJQUFBLFdBQUEsT0FBQSxLQUFBO1lBQ0EsT0FBQSxLQUFBLFlBQUE7WUFDQSxNQUFBO2dCQUNBLFFBQUE7Z0JBQ0EsS0FBQSxJQUFBLHFCQUFBLFdBQUEscUJBQUE7ZUFDQSxLQUFBLFNBQUEsZ0JBQUEsVUFBQTtlQUNBLFNBQUEsY0FBQSxVQUFBO29CQUNBLElBQUEsT0FBQTtvQkFDQSxPQUFBLEtBQUEsWUFBQTs7Ozs7QUFLQSIsImZpbGUiOiJtYWluLmpzIiwic291cmNlc0NvbnRlbnQiOlsiLyoqXG4gKiBAbmFtZXNwYWNlIGRpYXMudHJhbnNlY3RzXG4gKiBAZGVzY3JpcHRpb24gVGhlIERJQVMgdHJhbnNlY3RzIG1vZHVsZS5cbiAqL1xuYW5ndWxhci5tb2R1bGUoJ2RpYXMubGFzZXJwb2ludHMnLCBbJ2RpYXMuYXBpJywgJ2RpYXMudWknXSk7XG5cbi8qXG4gKiBEaXNhYmxlIGRlYnVnIGluZm8gaW4gcHJvZHVjdGlvbiBmb3IgYmV0dGVyIHBlcmZvcm1hbmNlLlxuICogc2VlOiBodHRwczovL2NvZGUuYW5ndWxhcmpzLm9yZy8xLjQuNy9kb2NzL2d1aWRlL3Byb2R1Y3Rpb25cbiAqL1xuYW5ndWxhci5tb2R1bGUoJ2RpYXMubGFzZXJwb2ludHMnKS5jb25maWcoZnVuY3Rpb24gKCRjb21waWxlUHJvdmlkZXIpIHtcbiAgICBcInVzZSBzdHJpY3RcIjtcblxuICAgICRjb21waWxlUHJvdmlkZXIuZGVidWdJbmZvRW5hYmxlZChmYWxzZSk7XG59KTtcbiIsIi8qKlxuICogQG5hbWVzcGFjZSBkaWFzLnRyYW5zZWN0c1xuICogQG5nZG9jIGNvbnRyb2xsZXJcbiAqIEBuYW1lIEZpbHRlckNvbnRyb2xsZXJcbiAqIEBtZW1iZXJPZiBkaWFzLnRyYW5zZWN0c1xuICogQGRlc2NyaXB0aW9uIENvbnRyb2xsZXIgZm9yIHRoZSBmaWx0ZXIgZmVhdHVyZSBvZiB0aGUgdHJhbnNlY3RzIHBhZ2VcbiAqL1xuYW5ndWxhci5tb2R1bGUoJ2RpYXMubGFzZXJwb2ludHMnKS5jb250cm9sbGVyKCdjb21wdXRlQXJlYUNvbnRyb2xsZXInLCBmdW5jdGlvbiAoJHNjb3BlLCRodHRwLFVSTCxtc2cpIHtcbiAgICAgICAgXCJ1c2Ugc3RyaWN0XCI7XG4gICAgICAgICRzY29wZS5kaXN0YW5jZSA9IG51bGw7XG4gICAgICAgICRzY29wZS5pc2NvbXB1dGluZyA9IGZhbHNlO1xuICAgICAgICAkc2NvcGUucmVxdWVzdD1mdW5jdGlvbihpbWFnZWlkLGRpc3RhbmNlKXtcbiAgICAgICAgICAgIGlmIChkaXN0YW5jZSA9PT0gdW5kZWZpbmVkKXtcbiAgICAgICAgICAgICAgICBkaXN0YW5jZSA9ICRzY29wZS5kaXN0YW5jZTtcbiAgICAgICAgICAgIH1cbiAgICAgICAgICAgICRzY29wZS5pc2NvbXB1dGluZz10cnVlO1xuICAgICAgICAgICAgJGh0dHAoe1xuICAgICAgICAgICAgICAgIG1ldGhvZDogJ1BPU1QnLFxuICAgICAgICAgICAgICAgIHVybDogVVJMK1wiL2FwaS92MS9pbWFnZXMvXCIraW1hZ2VpZCtcIi9sYXNlcnBvaW50cy9hcmVhL1wiK2Rpc3RhbmNlXG4gICAgICAgICAgICB9KS50aGVuKGZ1bmN0aW9uIHN1Y2Nlc3NDYWxsYmFjayhyZXNwb25zZSkge1xuICAgICAgICAgICAgICAgICAgICBtc2cuc3VjY2VzcyhcIlRoZSBhcmVhIHdpbGwgYmUgY29tcHV0ZWQgc29vbi5cIik7XG4gICAgICAgICAgICB9LCBmdW5jdGlvbiBlcnJvckNhbGxiYWNrKHJlc3BvbnNlKSB7XG4gICAgICAgICAgICAgICAgICAgIG1zZy5kYW5nZXIoXCJBbiBlcnJvciBvY2N1cmVkLiBJZiB5b3Uga2VlcCBnZXR0aW5nIHRoaXMgZXJyb3IgcGxlYXNlIGNvbnRhY3QgdGhlIGFkbWluaXN0cmF0b3IuXCIpO1xuICAgICAgICAgICAgICAgICAgICAkc2NvcGUuaXNjb21wdXRpbmc9ZmFsc2U7XG4gICAgICAgICAgICB9KTtcbiAgICAgICAgfTtcbiAgICB9XG4pO1xuIiwiLyoqXG4gKiBAbmFtZXNwYWNlIGRpYXMudHJhbnNlY3RzXG4gKiBAbmdkb2MgY29udHJvbGxlclxuICogQG5hbWUgRmlsdGVyQ29udHJvbGxlclxuICogQG1lbWJlck9mIGRpYXMudHJhbnNlY3RzXG4gKiBAZGVzY3JpcHRpb24gQ29udHJvbGxlciBmb3IgdGhlIGZpbHRlciBmZWF0dXJlIG9mIHRoZSB0cmFuc2VjdHMgcGFnZVxuICovXG5hbmd1bGFyLm1vZHVsZSgnZGlhcy50cmFuc2VjdHMnKS5jb250cm9sbGVyKCdjb21wdXRlQXJlYUNvbnRyb2xsZXJUcmFuc2VjdHMnLCBmdW5jdGlvbiAoJHNjb3BlLCRodHRwLFVSTCxtc2cpIHtcbiAgICAgICAgXCJ1c2Ugc3RyaWN0XCI7XG4gICAgICAgICRzY29wZS5kYXRhID0ge1xuICAgICAgICAgICAgZGlzdGFuY2U6IG51bGwsXG4gICAgICAgICAgICBpc2NvbXB1dGluZzpmYWxzZVxuICAgICAgICB9O1xuICAgICAgICAkc2NvcGUucmVxdWVzdD1mdW5jdGlvbih0cmFuc2VjdGlkKXtcbiAgICAgICAgICAgIG1zZy5zdWNjZXNzKFwiVGhlIGFyZWEgb2YgYWxsIGltYWdlcyB3aWxsIGJlIGNvbXB1dGVkIHNvb24uXCIpO1xuICAgICAgICAgICAgdmFyIGRpc3RhbmNlID0gJHNjb3BlLmRhdGEuZGlzdGFuY2U7XG4gICAgICAgICAgICAkc2NvcGUuZGF0YS5pc2NvbXB1dGluZz10cnVlO1xuICAgICAgICAgICAgJGh0dHAoe1xuICAgICAgICAgICAgICAgIG1ldGhvZDogJ1BPU1QnLFxuICAgICAgICAgICAgICAgIHVybDogVVJMK1wiL2FwaS92MS90cmFuc2VjdHMvXCIrdHJhbnNlY3RpZCtcIi9sYXNlcnBvaW50cy9hcmVhL1wiK2Rpc3RhbmNlXG4gICAgICAgICAgICB9KS50aGVuKGZ1bmN0aW9uIHN1Y2Nlc3NDYWxsYmFjayhyZXNwb25zZSkge1xuICAgICAgICAgICAgfSwgZnVuY3Rpb24gZXJyb3JDYWxsYmFjayhyZXNwb25zZSkge1xuICAgICAgICAgICAgICAgICAgICBtc2cuZGFuZ2VyKFwiQW4gZXJyb3Igb2NjdXJlZC4gSWYgeW91IGtlZXAgZ2V0dGluZyB0aGlzIGVycm9yIHBsZWFzZSBjb250YWN0IHRoZSBhZG1pbmlzdHJhdG9yLlwiKTtcbiAgICAgICAgICAgICAgICAgICAgJHNjb3BlLmRhdGEuaXNjb21wdXRpbmc9ZmFsc2U7XG4gICAgICAgICAgICB9KTtcbiAgICAgICAgfTtcbiAgICB9XG4pO1xuIl0sInNvdXJjZVJvb3QiOiIvc291cmNlLyJ9
