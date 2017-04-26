@unless ($volume->isRemote())
    @push('scripts')
        <script src="{{ cachebust_asset('vendor/laserpoints/scripts/main.js') }}"></script>
    @endpush
@endunless

<div class="col-sm-12 col-lg-6">
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">Laser points</h3>
        </div>
        @if ($volume->isRemote())
            <div class="panel-body text-muted">
                The laser point detection is not available for images of remote volumes.
            </div>
        @else
            <?php $img = \Biigle\Modules\Laserpoints\Image::convert($image); ?>
            @if ($img->laserpoints)
                <table class="table">
                    @if ($img->px)
                        <tr>
                            <th>Number of pixels</th>
                            <td>{{ $img->px }}</td>
                        </tr>
                    @endif

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
                    <div class="alert alert-danger" v-cloak v-else v-if="error" v-text="error"></div>
                    <form class="form-inline" v-if="!processing">
                        @if($img->laserpoints)
                            <div class="form-group">
                                <input class="form-control" v-model="distance" type="number" min="0" placeholder="New laser distance in cm" title="Distance between two laser points in cm. Leave empty to use the previously set distance ({{$img->distance}})"></input>
                            </div>
                            <div class="form-group">
                                <button class="btn btn-success" :disabled="loading" v-on:click.prevent="detect({{$image->id}}, {{$img->distance}})" title="Restart the laser point detection">Submit</button>
                            </div>
                        @else
                            <div class="form-group">
                                <input class="form-control" v-model="distance" type="number" min="0" placeholder="Laser distance in cm" title="Distance between two laser points in cm" required></input>
                            </div>
                            <div class="form-group">
                                <button class="btn btn-success" :disabled="loading || !distance" v-on:click.prevent="detect({{$image->id}})" title="Start a new laser point detection">Submit</button>
                            </div>
                        @endif
                    </form>
                @endcan
            @endif
        </div>
    </div>
</div>
