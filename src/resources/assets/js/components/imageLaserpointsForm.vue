<template>
    <form class="form-stacked" @submit.prevent="submit">
        <div class="form-group">
            <label for="distance">Laser distance in cm</label>
            <input v-model="distance" id="distance" type="number" min="1" step="0.1" title="Distance between two laser points in cm" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="label-typeahead">Laserpoint label</label>
            <typeahead
                :items="availableLabels"
                placeholder="Select a label for laserpoint annotations"
                @select="handleLabelSelect"
                title="Select the label used for laserpoint annotations"
                ></typeahead>
            <div v-if="!selectedLabel" class="text-danger small">
                Please select a label for laserpoint annotations.
            </div>
        </div>
        <div class="form-group">
            <button class="btn btn-success btn-block" title="Compute the area of this image." :disabled="submitDisabled || null">Submit</button>
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
 * Content of the laser points form for single image processing
 */
export default {
    mixins: [LoaderMixin],
    components: {
        typeahead: LabelTypeahead,
    },
    data() {
        return {
            imageId: null,
            distance: 1,
            selectedLabel: null,
            processing: false,
            error: false,
            availableLabels: [],
        };
    },
    computed: {
        submitDisabled() {
            return this.loading || this.processing || !this.distance || !this.selectedLabel;
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
        submit() {
            this.startLoading();
            LaserpointsApi.processImage({image_id: this.imageId}, {
                    distance: this.distance,
                    label_id: this.selectedLabel.id,
                })
                .then(this.setProcessing)
                .catch(this.handleError)
                .finally(this.finishLoading);
        },
        loadAvailableLabels() {
            // Load labels from image's volume project label trees
            try {
                const labelTrees = biigle.$require('images.labelTrees');
                
                if (labelTrees && Array.isArray(labelTrees)) {
                    this.availableLabels = labelTrees
                        .flatMap(tree => (tree.labels || []))
                        .filter(label => label && label.id && label.name)
                        .sort((a, b) => a.name.localeCompare(b.name));
                }
            } catch (error) {
                // If label trees are not available, try to fall back to volume label trees
                try {
                    const volumeLabelTrees = biigle.$require('volumes.labelTrees');
                    if (volumeLabelTrees && Array.isArray(volumeLabelTrees)) {
                        this.availableLabels = volumeLabelTrees
                            .flatMap(tree => (tree.labels || []))
                            .filter(label => label && label.id && label.name)
                            .sort((a, b) => a.name.localeCompare(b.name));
                    }
                } catch (fallbackError) {
                    // If no label trees are available, the component will show an empty typeahead
                    this.availableLabels = [];
                }
            }
        },
    },
    created() {
        this.imageId = biigle.$require('images.imageId');
        this.loadAvailableLabels();
    },
};
</script>
