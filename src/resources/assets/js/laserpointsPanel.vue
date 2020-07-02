<script>
import LaserpointsApi from './api/laserpoints';
import {handleErrorResponse} from './import';
import {LabelTypeahead} from './import';
import {LoaderMixin} from './import';
import {VolumesApi} from './import';

/**
 * The panel requesting a laser point detection on an individual image
 */
export default {
    mixins: [LoaderMixin],
    components: {
        typeahead: LabelTypeahead,
    },
    data() {
        return {
            image: null,
            distance: null,
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
        volumeId() {
            return this.image.volume_id;
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
            if (this.loading) return;

            this.startLoading();
            LaserpointsApi.processImage({image_id: this.image.id}, {
                    distance: this.distance,
                    label_id: this.label.id,
                })
                .then(this.setProcessing)
                .catch(this.handleError)
                .finally(this.finishLoading);
        },
    },
    created() {
        this.image = biigle.$require('laserpoints.image');
        this.distance = biigle.$require('laserpoints.distance');
    },
};
</script>
