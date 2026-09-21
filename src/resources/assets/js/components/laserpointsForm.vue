<template>
    <form class="form-stacked" @submit.prevent="submit">
        <div class="mode-row">
            <div class="btn-group btn-group-justified">
                <div class="btn-group">
                  <button
                    type="button"
                    class="btn btn-default"
                    :class="automaticButtonClass"
                    :disabled="manualOnly || null"
                    :title="automaticButtonTitle"
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
            <a
                v-if="manualUrl"
                :href="manualUrl"
                target="_blank"
                class="btn btn-default"
                title="Learn more about laser point detection"
                ><span class="fa fa-question-circle" aria-hidden="true"></span></a>
        </div>
        <div class="form-group">
            <label for="distance">Laser distance in cm</label>
            <input v-model="distance" id="distance" type="number" min="1" step="0.1" title="Distance between two laser points in cm" class="form-control" required>
        </div>
        <div class="form-group" v-show="!manualMode">
            <label>Number of laser points</label>
            <div class="btn-group btn-group-justified">
                <div class="btn-group" v-for="count in [2, 3, 4]" :key="count">
                    <button
                        type="button"
                        class="btn btn-default"
                        :class="{active: numLaserpoints === count}"
                        @click="numLaserpoints = count"
                        >{{ count }}</button>
                </div>
            </div>
        </div>
        <div class="form-group" v-show="!manualMode">
            <label for="channel_mode">Color channel</label>
            <select v-model="channelMode" id="channel_mode" class="form-control" title="Color channel that is used to find the laser points">
                <option v-if="!imageId" value="">Automatic</option>
                <option v-for="(label, mode) in channelModes" :key="mode" :value="mode" v-text="label"></option>
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
        manualOnly: {
            type: Boolean,
            default: false,
        },
        manualUrl: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            distance: null,
            numLaserpoints: 2,
            channelMode: '',
            channelModes: {
                gray: 'Gray',
                red: 'Red',
                green: 'Green',
                blue: 'Blue',
            },
            processing: false,
            error: false,
            labels: [],
            label: null,
            manualMode: this.manualOnly,
        };
    },
    computed: {
        submitDisabled() {
            if (this.manualMode) {
                return this.loading || this.processing || !this.distance || !this.label;
            }
            // For per-image automatic, channel_mode is required
            if (this.imageId) {
                return this.loading || this.processing || !this.distance || !this.channelMode;
            }
            // For volume automatic, channel_mode is optional (empty means automatic)
            return this.loading || this.processing || !this.distance;
        },
        automaticButtonClass() {
            return this.manualMode ? '' : 'active';
        },
        manualButtonClass() {
            return this.manualMode ? 'active' : '';
        },
        automaticButtonTitle() {
            if (this.manualOnly) {
                if (this.imageId) {
                    return 'The automatic laser point detection is not available for very large images';
                }

                return 'The automatic laser point detection is not available for volumes containing very large images';
            }

            return 'Detect the laser points automatically';
        },
    },
    methods: {
        selectAutomatic() {
            if (this.manualOnly) {
                return;
            }

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
        if (this.manualOnly) {
            return;
        }

        // For per-image detection, use the channel_mode of the previous detection.
        if (this.imageId) {
            this.channelMode = biigle.$require('laserpoints.channel_mode');
        }
    },
};
</script>

<style scoped>
    .btn-group-justified {
        margin-bottom: 15px;
    }

    .mode-row {
        display: flex;
        gap: 5px;
        margin-bottom: 15px;
    }

    .mode-row .btn-group-justified {
        flex: 1;
        margin-bottom: 0;
    }
</style>
