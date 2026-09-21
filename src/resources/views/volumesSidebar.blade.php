@if ($volume->isImageVolume())
    @can('edit-in', $volume)
        <sidebar-tab v-cloak name="laserpoints" icon="vector-square" title="Compute the area of each image in this volume">
            <component
                :is="plugins.laserpointsForm"
                :volume-id="{{$volume->id}}"
                :manual-only="{{$volume->hasTiledImages() ? 'true' : 'false'}}"
                ></component>
        </sidebar-tab>
    @endcan
@endif
