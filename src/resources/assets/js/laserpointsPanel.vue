<template>
    <div v-if="canEdit">
        <div class="alert alert-success" v-cloak v-if="processing">
            The laser point detection was submitted and will be available soon.
        </div>
        <div class="alert alert-danger" v-cloak v-else-if="error" v-text="error"></div>
        <form class="" v-if="!processing" v-on:submit.prevent="submit">
            <div class="form-group">
                <typeahead 
                    id="label" 
                    title="Label that was used to annotate laser points (optional for automatic detection)" 
                    placeholder="Laser point label (optional)" 
                    class="typeahead--block" 
                    :items="availableLabels" 
                    @select="handleLabelSelect"
                    :disabled="loading || processing"
                ></typeahead>
                <small class="text-muted">Select a label only if you have manually annotated laser points with that label. Leave empty for automatic detection.</small>
            </div>
            <div class="row">
                <div class="form-group col-xs-6">
                    <input class="form-control" v-model="distance" type="number" min="1" step="0.1" placeholder="Laser distance" title="Distance between two laser points in cm">
                </div>
                <div class="col-xs-6">
                    <button class="btn btn-success btn-block" :disabled="submitDisabled || null" title="Start a new laser point detection">Submit</button>
                </div>
            </div>
        </form>
    </div>
</template>

<script>
import LaserpointsApi from './api/laserpoints.js';
import {handleErrorResponse} from './import.js';
import {LoaderMixin} from './import.js';
import {LabelTypeahead} from './import.js';
import {VolumesApi} from './import.js';

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
            selectedLabel: null,
            availableLabels: [],
            processing: false,
            error: false,
            hasManualAnnotations: false,
        };
    },
    computed: {
        canEdit() {
            // For now, always allow editing. In a real implementation,
            // this would check user permissions for the volume
            return true;
        },
        submitDisabled() {
            // Only require label selection if there are manual annotations
            const labelRequired = this.hasManualAnnotations && !this.selectedLabel;
            return this.loading || this.processing || !this.distance || labelRequired;
        },
        volumeId() {
            return this.image ? this.image.volume_id : null;
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
        handleLabelSelect(label) {
            this.selectedLabel = label;
        },
        setLabels(response) {
            this.availableLabels = response.body;
        },
        loadAvailableLabels() {
            if (!this.loading && this.availableLabels.length === 0) {
                this.startLoading();
                VolumesApi.queryAnnotationLabels({id: this.volumeId})
                    .then(this.setLabels)
                    // Do not finish loading on error. If the labels can't be loaded,
                    // the form can't be submitted, too.
                    .then(this.finishLoading)
                    .catch(handleErrorResponse);
            }
        },
        checkForManualAnnotations() {
            // Check if the current image has any manual point annotations
            // This will help us determine if label selection should be required
            if (this.image && this.image.annotations) {
                this.hasManualAnnotations = this.image.annotations.some(annotation => 
                    annotation.shape_id === 4 && // Point shape ID is typically 4
                    annotation.labels && annotation.labels.length > 0
                );
            }
        },
        submit() {
            if (this.loading) return;

            this.startLoading();
            
            // Prepare the request data
            const requestData = {
                distance: this.distance,
            };
            
            // Only include label_id if a label is selected
            if (this.selectedLabel) {
                requestData.label_id = this.selectedLabel.id;
            }

            LaserpointsApi.processImage({image_id: this.image.id}, requestData)
                .then(this.setProcessing)
                .catch(this.handleError)
                .finally(this.finishLoading);
        },
    },
    created() {
        try {
            this.image = biigle.$require('laserpoints.image');
            
            if (this.image === undefined || this.image === null) {
                // As a fallback, try to get the image from annotations context
                try {
                    this.image = biigle.$require('annotations.image');
                } catch (e) {
                    console.warn("Could not load image from any context");
                }
            }
            
            this.distance = biigle.$require('laserpoints.distance');
        } catch (e) {
            console.warn("Error loading initial data:", e);
        }
        
        // Only proceed with loading labels after trying to get the image
        this.$nextTick(() => {
            this.loadAvailableLabels();
            this.checkForManualAnnotations();
        });
    },
};
</script>
