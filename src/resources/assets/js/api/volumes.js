/**
 * Resource for laser points in volumes.
 *
 * var resource = biigle.$require('laserpoints.api.volumes');
 *
 * Get IDs of all images of the volume that have laser points:
 * resource.query({id: 1).then(...);
 *
 * @type {Vue.resource}
 */
export default Vue.resource('api/v1/volumes{/id}/images/filter/laserpoints');
