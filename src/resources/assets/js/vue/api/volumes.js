/**
 * Resource for laserpoints in volumes.
 *
 * var resource = biigle.$require('laserpoints.api.volumes');
 *
 * Get IDs of all images of the volume that have laserpoints:
 * resource.query({id: 1).then(...);
 *
 * @type {Vue.resource}
 */
biigle.$declare('laserpoints.api.volumes', Vue.resource('api/v1/volumes{/id}/images/filter/laserpoints'));
