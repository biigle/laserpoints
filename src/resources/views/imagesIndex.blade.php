@push('scripts')
    <script src="{{ asset('vendor/laserpoints/scripts/main.js') }}"></script>
@endpush
<div class="col-sm-6 col-lg-4">
    <div class="panel panel-default" data-ng-app="dias.laserpoints" data-ng-controller="LaserpointsController">
        <div class="panel-heading">
            <h3 class="panel-title">Laserpoints</h3>
        </div>
        <?php $img = \Dias\Modules\Laserpoints\Image::convert($image) ?>
        @if ($img->laserpoints)
            <table class="table">
                @if ($img->px)
                    <tr>
                        <th>#Pixels</th>
                        <td>{{ $img->px }}</td>
                    </tr>
                @endif

                @if ($img->area)
                    <tr>
                        <th>Area</th>
                        <td>{{ round($img->area, 2) }} m²</td>
                    </tr>
                @endif

                @if ($img->count)
                    <tr>
                        <th>#Laserpoints</th>
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
        <div class="panel-body">
            @if (!$img->laserpoints)
                <div class="alert alert-info" data-ng-hide="isSubmitted()">
                    No laserpoint detection was performed yet.
                </div>
            @elseif($img->error)
                <div class="alert alert-danger" data-ng-hide="isSubmitted()">
                    The automatic laserpoint detection failed. Please annotate laserpoints manually.
                </div>
            @endif
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
        </div>
    </div>
</div>
