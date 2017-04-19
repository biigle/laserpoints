/**
 * The panel requesting a laserpoint detection on an individual image
 */
biigle.$viewModel('laserpoints-panel', function (element) {
    var messages = biigle.$require('messages.store');

    new Vue({
        el: element,
        mixins: [biigle.$require('core.mixins.loader')],
        data: {
            processing: false,
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
                    .catch(messages.handleErrorResponse)
                    .finally(this.finishLoading);
            },
            imageProcessing: function () {
                this.processing = true;
            },
        },
    });
});
