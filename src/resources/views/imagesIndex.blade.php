@unless ($transect->isRemote())
    @push('scripts')
        <script src="{{ cachebust_asset('vendor/laserpoints/scripts/main.js') }}"></script>
    @endpush
@endunless

<div class="col-sm-6 col-lg-4">
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">Laserpoints</h3>
        </div>
        @if ($transect->isRemote())
            <div class="panel-body text-muted">
                The laserpoint detection is not available for images of remote transects.
            </div>
        @else
            <?php $img = \Dias\Modules\Laserpoints\Image::convert($image); ?>
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
                            <th>Number of laserpoints</th>
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
                            <th>Distance between laserpoints</th>
                            <td>{{ $img->distance }} cm</td>
                        </tr>
                    @endif
                </table>
            @endif
            <div class="panel-body" data-ng-app="dias.laserpoints" data-ng-controller="LaserpointsController">
                @if (!$img->laserpoints)
                    <div class="alert alert-info" data-ng-hide="isSubmitted()">
                        No laserpoint detection was performed yet.
                    </div>
                @elseif ($img->error)
                    <div class="alert alert-danger" data-ng-hide="isSubmitted()">
                        @if ($img->message)
                            <strong>{{$img->message}}</strong>
                        @endif
                        The automatic laserpoint detection failed. You can always annotate the laserpoints manually and restart the detection.
                    </div>
                @endif
                @can('edit-in', $transect)
                    <form class="form-inline" data-ng-hide="isSubmitted()">
                        @if($img->laserpoints)
                            <div class="form-group">
                                <input class="form-control" data-ng-model="distance" id="distance" type="number" placeholder="New laser distance in cm" title="Distance between two laserpoints in cm. Leave empty to use the previously set distance ({{$img->distance}})"></input>
                            </div>
                            <div class="form-group">
                                <button class="btn btn-success" data-ng-disabled="isComputing()" data-ng-click="reDetection({{$image->id}}, {{$img->distance}})" title="Restart the laserpoint detection">Submit</button>
                            </div>
                        @else
                            <div class="form-group">
                                <input class="form-control" data-ng-model="distance" id="distance" type="number" placeholder="Laser distance in cm" title="Distance between two laserpoints in cm" required></input>
                            </div>
                            <div class="form-group">
                                <button class="btn btn-success" data-ng-disabled="isComputing()" data-ng-click="newDetection({{$image->id}})" title="Start a new laserpoint detection">Submit</button>
                            </div>
                        @endif
                    </form>
                    <div class="alert alert-success ng-cloak ng-hide" data-ng-show="isSubmitted()">
                        The laserpoint detection was submitted and will be available soon.
                    </div>
                @endcan
            @endif
        </div>
    </div>
</div>
