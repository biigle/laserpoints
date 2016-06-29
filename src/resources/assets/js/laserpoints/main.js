/**
 * @namespace dias.transects
 * @description The DIAS transects module.
 */
angular.module('dias.laserpoints', ['dias.api', 'dias.ui']);

/*
 * Disable debug info in production for better performance.
 * see: https://code.angularjs.org/1.4.7/docs/guide/production
 */
angular.module('dias.laserpoints').config(function ($compileProvider) {
    "use strict";

    $compileProvider.debugInfoEnabled(false);
});
