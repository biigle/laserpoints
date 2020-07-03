/**
 * Resource for the laser point detection.
 *
 * var resource = biigle.$require('api.laserpoints');
 *
 * Perform the laser point detection on all images of a volume:
 * resource.processVolume({volume_id: 1}, {distance: 50}).then(...);
 *
 * Perform the laser point detection on a single image:
 * resource.processImage({image_id: 1}, {distance: 50}).then(...);
 *
 * Get the laser point information for an image
 * resource.get({image_id: 1}).then(...);
 *
 * @type {Vue.resource}
 */
export default Vue.resource('api/v1/images{/image_id}/laserpoints', {}, {
    processVolume: {
        method: 'POST',
        url: 'api/v1/volumes{/volume_id}/laserpoints/area',
    },
    processImage: {
        method: 'POST',
        url: 'api/v1/images{/image_id}/laserpoints/area',
    },
});
