@if ($volume->isImageVolume())
    @can('edit-in', $volume)
        @if($volume->hasTiledImages())
            <sidebar-tab name="laserpoints" icon="vector-square" title="Laser point detection is not available for volumes with very large images" :disabled="true"></sidebar-tab>
        @else
            <sidebar-tab v-cloak name="laserpoints" icon="vector-square" title="Compute the area of each image in this volume">
                <a href="{{route('manual-tutorials', ['laserpoints', 'laserpoint-detection'])}}" target="_blank" class="btn btn-default btn-xs pull-right" title="What is this?"><span class="fa fa-info-circle" aria-hidden="true"></span></a>
                <component :is="plugins.laserpointsForm"></component>
            </sidebar-tab>
        @endif
    @endcan
@endif
