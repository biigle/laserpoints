@if ($volume->isImageVolume())
    @can('edit-in', $volume)
        @if($volume->hasTiledImages())
            <sidebar-tab name="laserpoints" icon="vector-square" title="Laser point detection is not available for volumes with very large images" :disabled="true"></sidebar-tab>
        @else
            <sidebar-tab v-cloak name="laserpoints" icon="vector-square" title="Compute the area of each image in this volume">
                <component
                    :is="plugins.laserpointsForm"
                    :volume-id="{{$volume->id}}"
                    ></component>
            </sidebar-tab>
        @endif
    @endcan
@endif
