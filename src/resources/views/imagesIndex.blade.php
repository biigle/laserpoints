<?php $img = \Biigle\Modules\Laserpoints\Image::convert($image); ?>

@unless ($volume->hasTiledImages())
    @push('scripts')
        {{vite_hot(base_path('vendor/biigle/laserpoints/hot'), ['src/resources/assets/js/main.js'], 'vendor/laserpoints')}}

        <script type="module">
            biigle.$declare('laserpoints.image', {!! $image->toJson() !!});
            biigle.$declare('laserpoints.distance', {!! $img->distance ?: 'null' !!});
        </script>
    @endpush
    @push('styles')
        {{vite_hot(base_path('vendor/biigle/laserpoints/hot'), ['src/resources/assets/sass/main.scss'], 'vendor/laserpoints')}}
    @endpush
@endunless

<div class="col-sm-12 col-lg-6">
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">Laser points</h3>
        </div>
        @if ($volume->hasTiledImages())
            <div class="panel-body text-muted">
                The laser point detection is not available for very large images.
            </div>
        @else
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
                            <td>{{ $img->method }}</td>
                        </tr>
                    @endif

                    @if ($img->distance)
                        <tr>
                            <th>Distance between laser points</th>
                            <td>{{ $img->distance }} cm</td>
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
                        The automatic laser point detection failed. You can always annotate the laser points manually and restart the detection.
                    </div>
                @endif
                @can('edit-in', $volume)
                    <div class="alert alert-success" v-cloak v-if="processing">
                        The laser point detection was submitted and will be available soon.
                    </div>
                    <div class="alert alert-danger" v-cloak v-else-if="error" v-text="error"></div>
                    <form class="" v-if="!processing" v-on:submit.prevent="submit">
                        <div class="form-group">
                            <typeahead id="label" title="Label that was used to annotate laser points" placeholder="Laser point label" class="typeahead--block" :items="labels" v-on:select="handleSelectLabel" v-on:focus="loadLabels">
                        </div>
                        <div class="row">
                            <div class="form-group col-xs-6">
                                <input class="form-control" v-model="distance" type="number" min="1" step="0.1" placeholder="Laser distance" title="Distance between two laser points in cm"></input>
                            </div>
                            <div class="col-xs-6">
                                <button class="btn btn-success btn-block" :disabled="submitDisabled" title="Start a new laser point detection">Submit</button>
                            </div>
                        </div>
                    </form>
                @endcan
            @endif
        </div>
    </div>
</div>
