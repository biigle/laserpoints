/**
 * Laser points filter for the volume overview filters.
 */
if (Array.isArray(biigle.$require('volumes.stores.filters'))) {
    biigle.$require('volumes.stores.filters').push({
        id: 'laserpoints',
        label: 'detected laser points',
        help: "All images that (don't) contain detected laser points.",
        listComponent: {
            mixins: [biigle.$require('volumes.components.filterListComponent')],
            data: function () {
                return {name: 'detected laser points'};
            },
        },
        getSequence: function (volumeId) {
            return biigle.$require('laserpoints.api.volumes').query({id: volumeId});
        }
    });
}
