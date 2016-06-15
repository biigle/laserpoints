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
            console.log($scope.distance);
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

//# sourceMappingURL=data:application/json;base64,eyJ2ZXJzaW9uIjozLCJzb3VyY2VzIjpbIm1haW4uanMiLCJjb250cm9sbGVycy9jb21wdXRlQXJlYUNvbnRyb2xsZXIuanMiXSwibmFtZXMiOltdLCJtYXBwaW5ncyI6IkFBQUE7Ozs7QUFJQSxRQUFBLE9BQUEsb0JBQUEsQ0FBQSxZQUFBOzs7Ozs7QUFNQSxRQUFBLE9BQUEsb0JBQUEsNEJBQUEsVUFBQSxrQkFBQTtJQUNBOztJQUVBLGlCQUFBLGlCQUFBOzs7Ozs7Ozs7O0FDTkEsUUFBQSxPQUFBLG9CQUFBLFdBQUEsMkRBQUEsVUFBQSxPQUFBLE1BQUEsSUFBQSxLQUFBO1FBQ0E7UUFDQSxPQUFBLFdBQUE7UUFDQSxPQUFBLGNBQUE7UUFDQSxPQUFBLFFBQUEsU0FBQSxRQUFBLFNBQUE7WUFDQSxRQUFBLElBQUEsT0FBQTtZQUNBLElBQUEsYUFBQSxVQUFBO2dCQUNBLFdBQUEsT0FBQTs7WUFFQSxPQUFBLFlBQUE7WUFDQSxNQUFBO2dCQUNBLFFBQUE7Z0JBQ0EsS0FBQSxJQUFBLGtCQUFBLFFBQUEscUJBQUE7ZUFDQSxLQUFBLFNBQUEsZ0JBQUEsVUFBQTtvQkFDQSxJQUFBLFFBQUE7ZUFDQSxTQUFBLGNBQUEsVUFBQTtvQkFDQSxJQUFBLE9BQUE7b0JBQ0EsT0FBQSxZQUFBOzs7OztBQUtBIiwiZmlsZSI6Im1haW4uanMiLCJzb3VyY2VzQ29udGVudCI6WyIvKipcbiAqIEBuYW1lc3BhY2UgZGlhcy50cmFuc2VjdHNcbiAqIEBkZXNjcmlwdGlvbiBUaGUgRElBUyB0cmFuc2VjdHMgbW9kdWxlLlxuICovXG5hbmd1bGFyLm1vZHVsZSgnZGlhcy5sYXNlcnBvaW50cycsIFsnZGlhcy5hcGknLCAnZGlhcy51aSddKTtcblxuLypcbiAqIERpc2FibGUgZGVidWcgaW5mbyBpbiBwcm9kdWN0aW9uIGZvciBiZXR0ZXIgcGVyZm9ybWFuY2UuXG4gKiBzZWU6IGh0dHBzOi8vY29kZS5hbmd1bGFyanMub3JnLzEuNC43L2RvY3MvZ3VpZGUvcHJvZHVjdGlvblxuICovXG5hbmd1bGFyLm1vZHVsZSgnZGlhcy5sYXNlcnBvaW50cycpLmNvbmZpZyhmdW5jdGlvbiAoJGNvbXBpbGVQcm92aWRlcikge1xuICAgIFwidXNlIHN0cmljdFwiO1xuXG4gICAgJGNvbXBpbGVQcm92aWRlci5kZWJ1Z0luZm9FbmFibGVkKGZhbHNlKTtcbn0pO1xuIiwiLyoqXG4gKiBAbmFtZXNwYWNlIGRpYXMudHJhbnNlY3RzXG4gKiBAbmdkb2MgY29udHJvbGxlclxuICogQG5hbWUgRmlsdGVyQ29udHJvbGxlclxuICogQG1lbWJlck9mIGRpYXMudHJhbnNlY3RzXG4gKiBAZGVzY3JpcHRpb24gQ29udHJvbGxlciBmb3IgdGhlIGZpbHRlciBmZWF0dXJlIG9mIHRoZSB0cmFuc2VjdHMgcGFnZVxuICovXG5hbmd1bGFyLm1vZHVsZSgnZGlhcy5sYXNlcnBvaW50cycpLmNvbnRyb2xsZXIoJ2NvbXB1dGVBcmVhQ29udHJvbGxlcicsIGZ1bmN0aW9uICgkc2NvcGUsJGh0dHAsVVJMLG1zZykge1xuICAgICAgICBcInVzZSBzdHJpY3RcIjtcbiAgICAgICAgJHNjb3BlLmRpc3RhbmNlID0gbnVsbDtcbiAgICAgICAgJHNjb3BlLmlzY29tcHV0aW5nID0gZmFsc2U7XG4gICAgICAgICRzY29wZS5yZXF1ZXN0PWZ1bmN0aW9uKGltYWdlaWQsZGlzdGFuY2Upe1xuICAgICAgICAgICAgY29uc29sZS5sb2coJHNjb3BlLmRpc3RhbmNlKTtcbiAgICAgICAgICAgIGlmIChkaXN0YW5jZSA9PT0gdW5kZWZpbmVkKXtcbiAgICAgICAgICAgICAgICBkaXN0YW5jZSA9ICRzY29wZS5kaXN0YW5jZTtcbiAgICAgICAgICAgIH1cbiAgICAgICAgICAgICRzY29wZS5pc2NvbXB1dGluZz10cnVlO1xuICAgICAgICAgICAgJGh0dHAoe1xuICAgICAgICAgICAgICAgIG1ldGhvZDogJ1BPU1QnLFxuICAgICAgICAgICAgICAgIHVybDogVVJMK1wiL2FwaS92MS9pbWFnZXMvXCIraW1hZ2VpZCtcIi9sYXNlcnBvaW50cy9hcmVhL1wiK2Rpc3RhbmNlXG4gICAgICAgICAgICB9KS50aGVuKGZ1bmN0aW9uIHN1Y2Nlc3NDYWxsYmFjayhyZXNwb25zZSkge1xuICAgICAgICAgICAgICAgICAgICBtc2cuc3VjY2VzcyhcIlRoZSBhcmVhIHdpbGwgYmUgY29tcHV0ZWQgc29vbi5cIik7XG4gICAgICAgICAgICB9LCBmdW5jdGlvbiBlcnJvckNhbGxiYWNrKHJlc3BvbnNlKSB7XG4gICAgICAgICAgICAgICAgICAgIG1zZy5kYW5nZXIoXCJBbiBlcnJvciBvY2N1cmVkLiBJZiB5b3Uga2VlcCBnZXR0aW5nIHRoaXMgZXJyb3IgcGxlYXNlIGNvbnRhY3QgdGhlIGFkbWluaXN0cmF0b3IuXCIpO1xuICAgICAgICAgICAgICAgICAgICAkc2NvcGUuaXNjb21wdXRpbmc9ZmFsc2U7XG4gICAgICAgICAgICB9KTtcbiAgICAgICAgfTtcbiAgICB9XG4pO1xuIl0sInNvdXJjZVJvb3QiOiIvc291cmNlLyJ9
