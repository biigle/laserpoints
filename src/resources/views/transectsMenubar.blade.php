<div>
        <button class="btn btn-default transect-menubar__item" data-popover-placement="right" data-uib-popover-template="'laserpointsPopover.html'" type="button" title="Compute the area of each image in this transect.">
            <span class="glyphicon glyphicon-sound-stereo" aria-hidden="true"></span>
        </button>
        <script type="text/ng-template" id="laserpointsPopover.html">
            <div class="background-segmentation-popup clearfix" data-ng-class="{help:show.help}" data-ng-controller="computeAreaControllerTransects">
                    <form class="form" data-ng-if="!data.iscomputing">
                        <label for="lpdistance">Laserpoint distance</label>
                        <input data-ng-model="data.distance" type="number"  id="lpdistance" title="Choose a distance between two laserpoints">
                    </form>
                    <button data-ng-click="request({{$transect->id}})" data-ng-if="!data.iscomputing" class="btn btn-success pull-right" title="Compute the area of each image in this transect.">Submit</button>
                    <div data-ng-if="data.iscomputing">
                        <i class="glyphicon glyphicon-repeat glyphicon-spin"></i> Computing...
                    </div>
            </div>
        </script>
</div>
