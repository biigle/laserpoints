<script>
import LaserpointsApi from './api/laserpoints.js';
import {handleErrorResponse} from './import.js';
import {LoaderMixin} from './import.js';

/**
 * The panel requesting a laser point detection on an individual image
 */
export default {
    mixins: [LoaderMixin],
    data() {
        return {
            image: null,
            distance: null,
            useLineDetection: true,
            processing: false,
            error: false,
        };
    },
    computed: {
        submitDisabled() {
            return this.loading || this.processing || !this.distance;
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
        submit() {
            if (this.loading) return;

            this.startLoading();
            LaserpointsApi.processImage({image_id: this.image.id}, {
                    distance: this.distance,
                    use_line_detection: this.useLineDetection,
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
