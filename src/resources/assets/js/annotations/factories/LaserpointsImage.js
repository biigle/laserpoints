/**
 * @ngdoc factory
 * @name LaserpointsImage
 * @memberOf dias.annotations
 * @description Provides the resource for image laserpoints
 * @requires $resource
 * @returns {Object} A new [ngResource](https://docs.angularjs.org/api/ngResource/service/$resource) object
 * @example
// get the laserpoint information of an image
var laserpoints = LaserpointsImage.get({image_id: 1}, function () {
   console.log(laserpoints); // {"points": [[10, 20], ...], "method": "heuristic", ...}
});
 *
 */
angular.module('dias.annotations').factory('LaserpointsImage', function ($resource, URL) {
    "use strict";

    return $resource(URL + '/api/v1/images/:image_id/laserpoints');
});
