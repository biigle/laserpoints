@can('edit-in', $volume)
    @if ($volume->isRemote())
        <button class="btn btn-default volume-menubar__item" title="Laserpoint detection is not available for remote volumes" disabled>
            <span class="glyphicon glyphicon-sound-stereo" aria-hidden="true"></span>
        </button>
    @else
        <div data-ng-controller="LaserpointsController">
            <button class="btn btn-default volume-menubar__item" data-popover-placement="right" data-uib-popover-template="'laserpointsPopover.html'" type="button" title="Compute the area of each image in this volume">
                <span class="glyphicon glyphicon-sound-stereo" aria-hidden="true"></span>
            </button>
        </div>
        <script type="text/ng-template" id="laserpointsPopover.html">
            <form class="" data-ng-hide="isSubmitted()">
                <div class="form-group">
                    <input data-ng-model="data.distance" type="number" id="distance" title="Distance between two laserpoints in cm" placeholder="Laser distance in cm" class="form-control" required>
                </div>
                <div class="form-group">
                    <button data-ng-click="newDetection()" class="btn btn-success" title="Compute the area of each image in this  volume." data-ng-disabled="isComputing()">Submit</button>
                </div>
            </form>
            <div class="alert alert-success ng-hide" data-ng-show="isSubmitted()">
                The laserpoint detection was submitted and will be available soon.
            </div>
        </script>
    @endif
@endcan
