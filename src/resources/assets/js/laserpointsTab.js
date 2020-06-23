import LaserpointsApi from './api/laserpoints';
import {handleErrorResponse} from './import';
import {LabelTypeahead} from './import';
import {LoaderMixin} from './import';
import {VolumesApi} from './import';

/**
 * Content of the laser points tab in the volume overview sidebar
 */
Vue.component('laserpoints-form', {
    mixins: [LoaderMixin],
    components: {
        typeahead: LabelTypeahead,
    },
    data() {
        return {
            volumeId: null,
            distance: 1,
            processing: false,
            error: false,
            labels: [],
            label: null,
        };
    },
    computed: {
        submitDisabled() {
            return this.loading || this.processing || !this.distance || !this.label;
        },
    },
    methods: {
        handleError(response) {
            if (response.status === 422 && response.body.errors && response.body.errors.id) {
                this.error = response.body.errors.id.join("\n");
                this.processing = false;
            } else {
                handleErrorResponse(response);
            }
        },
        setProcessing() {
            this.processing = true;
            this.error = false;
        },
        setLabels(response) {
            this.labels = response.body;
        },
        handleSelectLabel(label) {
            this.label = label;
        },
        loadLabels() {
            if (!this.loading && this.labels.length === 0) {
                this.startLoading();
                VolumesApi.queryAnnotationLabels({id: this.volumeId})
                    .then(this.setLabels)
                    // Do not finish loading on error. If the labels can't be loaded,
                    // the form can't be submitted, too.
                    .then(this.finishLoading)
                    .catch(handleErrorResponse);
            }
        },
        submit() {
            this.startLoading();
            LaserpointsApi.processVolume({volume_id: this.volumeId}, {
                    distance: this.distance,
                    label_id: this.label.id,
                })
                .then(this.setProcessing)
                .catch(this.handleError)
                .finally(this.finishLoading);
        },
    },
    created() {
        this.volumeId = biigle.$require('volumes.volumeId');
    },
});
