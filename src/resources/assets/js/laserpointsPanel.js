/**
 * The panel requesting a laser point detection on an individual image
 */
biigle.$viewModel('laserpoints-panel', function (element) {
    var IMAGE = biigle.$require('laserpoints.image');
    var DISTANCE = biigle.$require('laserpoints.distance');

    new Vue({
        el: element,
        mixins: [biigle.$require('core.mixins.loader')],
        components: {
            typeahead: biigle.$require('labelTrees.components.labelTypeahead'),
        },
        data: {
            volumeId: IMAGE.volume_id,
            distance: DISTANCE,
            processing: false,
            error: false,
            labels: [],
            label: null,
        },
        computed: {
            submitDisabled: function () {
                return this.loading || this.processing || !this.distance || !this.label;
            },
        },
        methods: {
            handleError: function (response) {
                if (response.status === 422 && response.body.errors && response.body.errors.id) {
                    this.error = response.body.errors.id.join("\n");
                    this.processing = false;
                } else {
                    biigle.$require('messages.store').handleErrorResponse(response);
                }
            },
            setProcessing: function () {
                this.processing = true;
                this.error = false;
            },
            setLabels: function (response) {
                this.labels = response.body;
            },
            handleSelectLabel: function (label) {
                this.label = label;
            },
            loadLabels: function () {
                if (!this.loading && this.labels.length === 0) {
                    this.startLoading();
                    biigle.$require('annotations.api.volumes')
                        .queryAnnotationLabels({id: this.volumeId})
                        .then(this.setLabels)
                        // Do not finish loading on error. If the labels can't be loaded,
                        // the form can't be submitted, too.
                        .then(this.finishLoading)
                        .catch(biigle.$require('messages.store').handleErrorResponse);
                }
            },
            submit: function () {
                if (this.loading) return;

                this.startLoading();
                biigle.$require('api.laserpoints')
                    .processImage({image_id: IMAGE.id}, {
                        distance: this.distance,
                        label_id: this.label.id,
                    })
                    .then(this.setProcessing)
                    .catch(this.handleError)
                    .finally(this.finishLoading);
            },
        },
    });
});
