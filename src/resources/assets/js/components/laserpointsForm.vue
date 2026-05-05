<template>
    <form class="form-stacked" @submit.prevent="submit">
        <div class="btn-group btn-group-justified">
            <div class="btn-group">
              <button
                type="button"
                class="btn btn-default"
                :class="automaticButtonClass"
                @click="selectAutomatic"
                >Automatic</button>
            </div>
            <div class="btn-group">
              <button
                type="button"
                class="btn btn-default"
                :class="manualButtonClass"
                @click="selectManual"
                >Manual</button>
            </div>
        </div>
        <div class="form-group">
            <label for="distance">Laser distance in cm</label>
            <input v-model="distance" id="distance" type="number" min="1" step="0.1" title="Distance between two laser points in cm" class="form-control" required>
        </div>
        <div class="form-group" v-show="!manualMode">
            <label for="num_laserpoints">Number of laser points</label>
            <input v-model.number="numLaserpoints" id="num_laserpoints" type="number" min="1" step="1" title="Number of laser points to detect" class="form-control" required>
        </div>
        <div class="form-group" v-if="imageId && !manualMode">
            <label for="channel_mode">Color channel</label>
            <select v-model="channelMode" id="channel_mode" title="Color channel to use for laser point detection." class="form-control" required>
                <option value="">Select channel...</option>
                <option value="red">Red</option>
                <option value="green">Green</option>
                <option value="blue">Blue</option>
                <option value="gray">Gray</option>
            </select>
        </div>
        <div v-show="manualMode" class="form-group">
            <label for="label">Laser point label</label>
            <typeahead id="label" title="Laser point" placeholder="Laser point label" class="typeahead--block" :items="labels" @select="handleSelectLabel" @focus="loadLabels"></typeahead>
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
    props: {
        volumeId: {
            type: Number,
            required: true,
        },
        imageId: {
            type: Number,
            default: null,
        },
    },
    data() {
        return {
            distance: null,
            numLaserpoints: 2,
            channelMode: '',
            processing: false,
            error: false,
            labels: [],
            label: null,
            manualMode: false,
        };
    },
    computed: {
        submitDisabled() {
            if (this.manualMode) {
                return this.loading || this.processing || !this.distance || !this.label;
            }
            // For per-image automatic, channel_mode is required
            if (this.imageId && !this.manualMode) {
                return this.loading || this.processing || !this.distance || !this.channelMode;
            }
            // For volume automatic, channel_mode is not required
            return this.loading || this.processing || !this.distance;
        },
        automaticButtonClass() {
            return this.manualMode ? '' : 'active';
        },
        manualButtonClass() {
            return this.manualMode ? 'active' : '';
        },
    },
    methods: {
        selectAutomatic() {
            this.manualMode = false;
        },
        selectManual() {
            this.manualMode = true;
        },
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
            let promise;
            if (this.manualMode) {
                const payload = {
                    distance: this.distance,
                    label_id: this.label.id,
                };

                if (this.imageId) {
                    promise = LaserpointsApi.processImageManual({image_id: this.imageId}, payload);
                } else {
                    promise = LaserpointsApi.processVolumeManual({volume_id: this.volumeId}, payload);
                }
            } else {
                const payload = {
                    distance: this.distance,
                    num_laserpoints: this.numLaserpoints,
                };
                if (this.channelMode) {
                    payload.channel_mode = this.channelMode;
                }

                if (this.imageId) {
                    promise = LaserpointsApi.processImageAutomatic({image_id: this.imageId}, payload);
                } else {
                    promise = LaserpointsApi.processVolumeAutomatic({volume_id: this.volumeId}, payload);
                }
            }

            promise.then(this.setProcessing)
                .catch(this.handleError)
                .finally(this.finishLoading);
        },
    },
    mounted() {
        // For per-image detection, use previous channel_mode if available, otherwise default to gray
        if (this.imageId && window.biigle && window.biigle.laserpoints && window.biigle.laserpoints.channel_mode) {
            this.channelMode = window.biigle.laserpoints.channel_mode;
        } else if (this.imageId) {
            this.channelMode = 'gray';
        }
    },
};
</script>

<style scoped>
    .btn-group-justified {
        margin-bottom: 15px;
    }
</style>
