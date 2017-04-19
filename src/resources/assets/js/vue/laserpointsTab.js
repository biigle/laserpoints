/**
 * Content of the laserpoints tab in the volume overview sidebar
 */
Vue.component('laserpoints-form', {
    mixins: [biigle.$require('core.mixins.loader')],
    data: function () {
        return {
            volumeId: biigle.$require('volumes.volumeId'),
            distance: 1,
            processing: false,
        };
    },
    computed: {
        submitDisabled: function () {
            return this.loading || this.processing;
        },
    },
    methods: {
        submit: function () {
            this.startLoading();
            biigle.$require('api.laserpoints')
                .processVolume({volume_id: this.volumeId}, {distance: this.distance})
                .then(this.volumeProcessing)
                .catch(biigle.$require('messages.store').handleErrorResponse)
                .finally(this.finishLoading);
        },
        volumeProcessing: function () {
            this.processing = true;
        },
    },
});
