import VolumesApi from './api/volumes.js';
import {FilterList} from './import.js';
import {VolumeFilters} from './import.js';

/**
 * Laser points filter for the volume overview filters.
 */
if (Array.isArray(VolumeFilters)) {
    VolumeFilters.push({
        id: 'laserpoints',
        types: ['image'],
        label: 'detected laser points',
        help: "All images that (don't) contain detected laser points.",
        listComponent: {
            mixins: [FilterList],
            data() {
                return {name: 'detected laser points'};
            },
        },
        getSequence(volumeId) {
            return VolumesApi.query({id: volumeId});
        },
    });
}
