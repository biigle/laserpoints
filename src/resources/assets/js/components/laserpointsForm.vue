<template>
    <form class="form-stacked" @submit.prevent="submit">
        <div class="form-group">
            <label for="distance">Laser distance in cm</label>
            <input v-model="distance" id="distance" type="number" min="1" step="0.1" title="Distance between two laser points in cm" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="label-typeahead">Laserpoint label (optional)</label>
            <typeahead
                :items="availableLabels"
                placeholder="Select a label for laserpoint annotations (optional)"
                @select="handleLabelSelect"
                title="Select the label used for laserpoint annotations (only needed if you have manual annotations)"
                ></typeahead>
            <small class="text-muted">Select a label only if you have manually annotated laser points with that label. Leave empty for automatic detection.</small>
        </div>
        <div class="form-group">
            <div class="checkbox">
                <label>
                    <input v-model="useLineDetection" type="checkbox" title="Use line fitting to improve detection accuracy">
                    Use line detection mode
                    <span class="text-muted">(recommended for better accuracy, refer to the manual for limitations)</span>
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
import {LabelTypeahead} from '../import.js';

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
            selectedLabel: null,
            useLineDetection: true,
            processing: false,
            error: false,
            availableLabels: [],
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
        handleLabelSelect(label) {
            this.selectedLabel = label;
        },
        handleLabelDeselect() {
            this.selectedLabel = null;
        },
        submit() {
            this.startLoading();
            
            // Prepare the request data
            const requestData = {
                distance: this.distance,
                use_line_detection: this.useLineDetection,
            };
            
            // Only include label_id if a label is selected
            if (this.selectedLabel) {
                requestData.label_id = this.selectedLabel.id;
            }

            LaserpointsApi.processVolume({volume_id: this.volumeId}, requestData)
                .then(this.setProcessing)
                .catch(this.handleError)
                .finally(this.finishLoading);
        },
        loadAvailableLabels() {
            // Load labels from volume's project label trees
            try {
                const labelTrees = biigle.$require('volumes.labelTrees');
                
                if (labelTrees && Array.isArray(labelTrees)) {
                    this.availableLabels = labelTrees
                        .flatMap(tree => (tree.labels || []))
                        .filter(label => label && label.id && label.name)
                        .sort((a, b) => a.name.localeCompare(b.name));
                }
            } catch (error) {
                // If label trees are not available, the component will show an empty dropdown
                this.availableLabels = [];
            }
        },
    },
    created() {
        this.volumeId = biigle.$require('volumes.volumeId');
        this.loadAvailableLabels();
    },
};
</script>
