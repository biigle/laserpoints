<?php $img = \Biigle\Modules\Laserpoints\Image::convert($image); ?>

@push('scripts')
    {{vite_hot(base_path('vendor/biigle/laserpoints/hot'), ['src/resources/assets/js/main.js'], 'vendor/laserpoints')}}

    <script type="module">
        biigle.$declare('laserpoints.distance', {!! $img->distance ?: 'null' !!});
        biigle.$declare('laserpoints.channel_mode', {!! json_encode($img->channel_mode ?: '') !!});
    </script>
@endpush

<div class="col-sm-12 col-lg-6">
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">Laser points</h3>
        </div>
        @if ($img->laserpoints)
            <table class="table">
                @if ($img->area)
                    <tr>
                        <th>Area covered by the image</th>
                        <td>{{ round($img->area, 2) }} m²</td>
                    </tr>
                @endif

                @if ($img->count)
                    <tr>
                        <th>Number of laser points</th>
                        <td>{{ $img->count }}</td>
                    </tr>
                @endif

                @if ($img->method)
                    <tr>
                        <th>Detection method</th>
                        {{-- Results of the former Delphi detection store other method names. --}}
                        <td>{{ $img->method === 'manual' ? 'manual' : 'automatic' }}</td>
                    </tr>
                @endif

                @if ($img->distance)
                    <tr>
                        <th>Distance between laser points</th>
                        <td>{{ $img->distance }} cm</td>
                    </tr>
                @endif

                @if ($img->channel_mode)
                    <tr>
                        <th>Color channel</th>
                        <td>{{ ucfirst($img->channel_mode) }}</td>
                    </tr>
                @endif
            </table>
        @endif
        <div id="laserpoints-panel" class="panel-body">
            @if (!$img->laserpoints)
                <div class="alert alert-info" v-if="!processing">
                    No laser point detection was performed yet.
                </div>
            @elseif ($img->error)
                <div class="alert alert-danger" v-if="!processing">
                    @if ($img->message)
                        <strong>{{$img->message}}</strong>
                    @endif
                    @if ($img->method === 'manual')
                        The laser point detection failed. Please check the manually annotated laser points and restart the detection.
                    @else
                        The automatic laser point detection failed. You can always annotate the laser points manually and restart the detection.
                    @endif
                </div>
            @endif
            @can('edit-in', $volume)
                <laserpoints-form
                    :volume-id="{{$img->volume_id}}"
                    :image-id="{{$img->id}}"
                    :manual-only="{{$img->tiled ? 'true' : 'false'}}"
                    manual-url="{{route('manual-tutorials', ['laserpoints', 'laserpoint-detection'])}}"
                    ></laserpoints-form>
            @endcan
        </div>
    </div>
</div>
