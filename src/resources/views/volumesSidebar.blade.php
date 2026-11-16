@if ($volume->isImageVolume())
    @can('edit-in', $volume)
        <sidebar-tab v-cloak name="laserpoints" icon="vector-square" title="Compute the area of each image in this volume">
            <component
                :is="plugins.laserpointsForm"
                :volume-id="{{$volume->id}}"
                :manual-only="{{$volume->hasTiledImages() ? 'true' : 'false'}}"
                manual-url="{{route('manual-tutorials', ['laserpoints', 'laserpoint-detection'])}}"
                ></component>
            <component
                :is="plugins.laserpointsStatistics"
                :volume-id="{{$volume->id}}"
                ></component>
        </sidebar-tab>
    @endcan
@endif
