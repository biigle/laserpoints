import { Resource } from '../import.js';

/**
 * Resource for the laser point detection.
 *
 * var resource = biigle.$require('api.laserpoints');
 *
 * Perform the laser point detection on all images of a volume:
 * resource.processVolumeAutomatic({volume_id: 1}, {distance: 50}).then(...);
 *
 * Perform the laser point detection on a single image:
 * resource.processImageAutomatic({image_id: 1}, {distance: 50}).then(...);
 *
 * Get the laser point information for an image
 * resource.get({image_id: 1}).then(...);
 */
export default Resource('api/v1/images{/image_id}/laserpoints', {}, {
    processVolumeAutomatic: {
        method: 'POST',
        url: 'api/v1/volumes{/volume_id}/laserpoints/automatic',
    },
    processImageAutomatic: {
        method: 'POST',
        url: 'api/v1/images{/image_id}/laserpoints/automatic',
    },
    processVolumeManual: {
        method: 'POST',
        url: 'api/v1/volumes{/volume_id}/laserpoints/manual',
    },
    processImageManual: {
        method: 'POST',
        url: 'api/v1/images{/image_id}/laserpoints/manual',
    },
});
