/**
 * Laserpoints filter for the volume overview filters.
 */
if (Array.isArray(biigle.$require('volumes.stores.filters'))) {
    biigle.$require('volumes.stores.filters').push({
        id: 'laserpoints',
        label: 'detected laserpoints',
        help: "All images that (don't) contain detected laserpoints.",
        listComponent: {
            mixins: [biigle.$require('volumes.components.filterListComponent')],
            data: function () {
                return {name: 'detected laserpoints'};
            },
        },
        getSequence: function (volumeId) {
            return biigle.$require('laserpoints.api.volumes').query({id: volumeId});
        }
    });
}
