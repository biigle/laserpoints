/**
 * @namespace biigle.volumes
 * @description The BIIGLE volumes module.
 */
angular.module('biigle.laserpoints', ['biigle.api', 'biigle.ui']);

/*
 * Disable debug info in production for better performance.
 * see: https://code.angularjs.org/1.4.7/docs/guide/production
 */
angular.module('biigle.laserpoints').config(function ($compileProvider) {
    "use strict";

    $compileProvider.debugInfoEnabled(false);
});
