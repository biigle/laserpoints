/**
 * Content of the laser points tab in the volume overview sidebar
 */
Vue.component('laserpoints-form', {
    mixins: [biigle.$require('core.mixins.loader')],
    components: {
        typeahead: biigle.$require('labelTrees.components.labelTypeahead'),
    },
    data: function () {
        return {
            volumeId: biigle.$require('volumes.volumeId'),
            distance: 1,
            processing: false,
            error: false,
            labels: [],
            label: null,
        };
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
            this.startLoading();
            biigle.$require('api.laserpoints')
                .processVolume({volume_id: this.volumeId}, {
                    distance: this.distance,
                    label_id: this.label.id,
                })
                .then(this.setProcessing)
                .catch(this.handleError)
                .finally(this.finishLoading);
        },
    },
});
