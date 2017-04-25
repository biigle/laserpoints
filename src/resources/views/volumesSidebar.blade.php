@can('edit-in', $volume)
    @if ($volume->isRemote())
        <sidebar-tab name="laserpoints" icon="sound-stereo" title="Laserpoint detection is not available for remote volumes" :disabled="true"></sidebar-tab>
    @else
        <sidebar-tab name="laserpoints" icon="sound-stereo" title="Compute the area of each image in this volume">
            <laserpoints-form v-cloak inline-template>
                <form class="form-stacked" v-on:submit.prevent="submit">
                    <div class="form-group">
                        <label for="distance">Laser distance in cm</label>
                        <input v-model="distance" id="distance" type="number" min="1" title="Distance between two laserpoints in cm" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <button class="btn btn-success btn-block" title="Compute the area of each image in this  volume." :disabled="submitDisabled">Submit</button>
                    </div>
                    <div class="alert alert-success" v-if="processing">
                        The laserpoint detection was submitted and will be available soon.
                    </div>
                    <div class="alert alert-danger" v-else v-if="error" v-text="error"></div>
                </form>
            </laserpoints-form>
        </sidebar-tab>
    @endif
@endcan
