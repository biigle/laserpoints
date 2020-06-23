import VolumesApi from './api/volumes';
import {FilterList} from './import';
import {VolumeFilters} from './import';

/**
 * Laser points filter for the volume overview filters.
 */
if (Array.isArray(VolumeFilters)) {
    VolumeFilters.push({
        id: 'laserpoints',
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
