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
            error: false,
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
                .catch(this.handleError)
                .finally(this.finishLoading);
        },
        handleError: function (response) {
            if (response.status === 422 && response.data.id) {
                this.error = response.data.id;
            } else {
                biigle.$require('messages.store').handleErrorResponse(response);
            }
        },
        volumeProcessing: function () {
            this.processing = true;
            this.error = false;
        },
    },
});
