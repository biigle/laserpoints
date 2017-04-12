@can('edit-in', $volume)
    @if ($volume->isRemote())
        <sidebar-tab name="laserpoints" icon="sound-stereo" title="Laserpoint detection is not available for remote volumes" :disabled="true"></sidebar-tab>
    @else
        <sidebar-tab name="laserpoints" icon="sound-stereo" title="Compute the area of each image in this volume">
            <laserpoints-form v-cloak inline-template>
                <form class="form-inline" v-on:submit.prevent="submit">
                    <div class="form-group">
                        <input v-model="distance" type="number" min="0" title="Distance between two laserpoints in cm" placeholder="Laser distance in cm" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <button class="btn btn-success" title="Compute the area of each image in this  volume." :disabled="loading">Submit</button>
                    </div>
                    <div class="alert alert-success">
                        The laserpoint detection was submitted and will be available soon.
                    </div>
                </form>
            </laserpoints-form>
        </sidebar-tab>
    @endif
@endcan
