<template>
    <form class="form-stacked" @submit.prevent="submit">
        <div class="form-group">
            <label for="distance">Laser distance in cm</label>
            <input v-model="distance" id="distance" type="number" min="1" step="0.1" title="Distance between two laser points in cm" class="form-control" required>
        </div>
        <div class="form-group">
            <div class="checkbox">
                <label>
                    <input v-model="useLineDetection" type="checkbox" title="Use line fitting to improve detection accuracy">
                    Use line detection mode
                    <span class="text-muted">(recommended for better accuracy)</span>
                </label>
            </div>
        </div>
        <div class="form-group">
            <button class="btn btn-success btn-block" title="Compute the area of each image in this volume." :disabled="submitDisabled || null">Submit</button>
        </div>
        <div class="alert alert-success" v-if="processing">
            The laser point detection was submitted and will be available soon.
        </div>
        <div class="alert alert-danger" v-else-if="error" v-text="error"></div>
    </form>
</template>
<script>
import LaserpointsApi from '../api/laserpoints.js';
import {handleErrorResponse} from '../import.js';
import {LoaderMixin} from '../import.js';

/**
 * Content of the laser points tab in the volume overview sidebar
 */
export default {
    mixins: [LoaderMixin],
    data() {
        return {
            volumeId: null,
            distance: 1,
            useLineDetection: true,
            processing: false,
            error: false,
        };
    },
    computed: {
        submitDisabled() {
            return this.loading || this.processing || !this.distance;
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
            this.startLoading();
            LaserpointsApi.processVolume({volume_id: this.volumeId}, {
                    distance: this.distance,
                    use_line_detection: this.useLineDetection,
                })
                .then(this.setProcessing)
                .catch(this.handleError)
                .finally(this.finishLoading);
        },
    },
    created() {
        this.volumeId = biigle.$require('volumes.volumeId');
    },
};
</script>
