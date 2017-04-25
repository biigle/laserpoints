/**
 * The panel requesting a laserpoint detection on an individual image
 */
biigle.$viewModel('laserpoints-panel', function (element) {

    new Vue({
        el: element,
        mixins: [biigle.$require('core.mixins.loader')],
        data: {
            processing: false,
            error: false,
            distance: null,
        },
        methods: {
            detect: function (id, distance) {
                if (this.loading) return;

                distance = this.distance || distance;
                this.startLoading();
                biigle.$require('api.laserpoints')
                    .processImage({image_id: id}, {distance: distance})
                    .then(this.imageProcessing)
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
            imageProcessing: function () {
                this.processing = true;
                this.error = false;
            },
        },
    });
});
