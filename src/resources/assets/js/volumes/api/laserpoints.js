/**
 * Resource for the laserpoint detection.
 *
 * var resource = biigle.$require('api.laserpoints');
 *
 * Perform the laserpoint detection on all images of a volume:
 * resource.processVolume({volume_id: 1}, {distance: 50}).then(...);
 *
 * Perform the laserpoint detection on a single image:
 * resource.processImage({image_id: 1}, {distance: 50}).then(...);
 *
 * Get the laserpoint information for an image
 * resource.get({image_id: 1}).then(...);
 *
 * @type {Vue.resource}
 */
biigle.$declare('api.laserpoints', Vue.resource('api/v1/images{/image_id}/laserpoints', {}, {
    processVolume: {
        method: 'POST',
        url: 'api/v1/volumes{/volume_id}/laserpoints/area',
    },
    processImage: {
        method: 'POST',
        url: 'api/v1/images{/image_id}/laserpoints/area',
    },
}));
