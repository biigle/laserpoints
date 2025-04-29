<template>
    <form class="form-stacked" @submit.prevent="submit">
        <div class="form-group">
            <label for="label">Laser point label</label>
            <typeahead id="label" title="Laser point" placeholder="Laser point label" class="typeahead--block" :items="labels" @select="handleSelectLabel" @focus="loadLabels"></typeahead>
        </div>
        <div class="form-group">
            <label for="distance">Laser distance in cm</label>
            <input v-model="distance" id="distance" type="number" min="1" step="0.1" title="Distance between two laser points in cm" class="form-control" required>
        </div>
        <div class="form-group">
            <button class="btn btn-success btn-block" title="Compute the area of each image in this  volume." :disabled="submitDisabled || null">Submit</button>
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
import {LabelTypeahead} from '../import.js';
import {LoaderMixin} from '../import.js';
import {VolumesApi} from '../import.js';

/**
 * Content of the laser points tab in the volume overview sidebar
 */
export default {
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
};
</script>
