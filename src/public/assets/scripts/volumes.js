angular.module("biigle.volumes").controller("LaserpointsController",["$scope","$http","URL","msg","VOLUME_ID",function(t,n,e,a,i){"use strict";var o=!1,r=!1;t.data={distance:null};var s=function(){r=!0},u=function(t){o=!1,a.responseError(t)};t.isComputing=function(){return o},t.isSubmitted=function(){return r},t.newDetection=function(){!t.data.distance||t.data.distance<=0||(o=!0,n({method:"POST",url:e+"/api/v1/volumes/"+i+"/laserpoints/area",data:{distance:t.data.distance}}).then(s,u))}}]);