@can('edit-in', $volume)
    @if($volume->hasTiledImages())
        <sidebar-tab name="laserpoints" icon="sound-stereo" title="Laser point detection is not available for volumes with very large images" :disabled="true"></sidebar-tab>
    @else
        <sidebar-tab v-cloak name="laserpoints" icon="sound-stereo" title="Compute the area of each image in this volume">
            <a href="{{route('manual-tutorials', ['laserpoints', 'laserpoint-detection'])}}" target="_blank" class="btn btn-default btn-xs pull-right" title="What is this?"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span></a>
            <laserpoints-form inline-template>
                <form class="form-stacked" v-on:submit.prevent="submit">
                    <div class="form-group">
                        <label for="distance">Laser distance in cm</label>
                        <input v-model="distance" id="distance" type="number" min="1" title="Distance between two laser points in cm" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <button class="btn btn-success btn-block" title="Compute the area of each image in this  volume." :disabled="submitDisabled">Submit</button>
                    </div>
                    <div class="alert alert-success" v-if="processing">
                        The laser point detection was submitted and will be available soon.
                    </div>
                    <div class="alert alert-danger" v-else v-if="error" v-text="error"></div>
                </form>
            </laserpoints-form>
        </sidebar-tab>
    @endif
@endcan
