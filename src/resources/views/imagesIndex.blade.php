@push('scripts')
    <script src="{{ asset('vendor/laserpoints/scripts/main.js') }}"></script>
@endpush
<div class="col-sm-6 col-lg-4">
    <div class="panel panel-default" data-ng-app="dias.laserpoints" data-ng-controller="computeAreaController">
        <div class="panel-heading">
            <h3 class="panel-title">Laserpoints</h3>
        </div>
        <table class="table">
            <tr>
                <th>#Pixels</th>
                <td>{{ json_decode($image->metainfo)->px }}</td>
            </tr>
            <tr>
                <th>Area</th>
                <td>{{ json_decode($image->metainfo)->area }} m<sup>2</sup></td>
            </tr>
            <tr>
                <th>#Laserpoints</th>
                <td>{{ json_decode($image->metainfo)->numLaserpoints }}</td>
            </tr>
            @if (isset(json_decode($image->metainfo)->laserdist))
            <tr>
                <th>Detection Type</th>
                <td>{{ json_decode($image->metainfo)->detection }}</td>
            </tr>
            @endif
            @if (isset(json_decode($image->metainfo)->laserdist))
            <tr>
                <th>Distance of laserpoints</th>
                <td>{{ json_decode($image->metainfo)->laserdist }}</td>
            </tr>
            @endif
            <tr>
                <th>Operations</th>
                <td></td>
            </tr>
            @if (isset(json_decode($image->metainfo)->laserdist))
                <tr>
                    <th>Compute Area</th>
                    <td><button class="btn-primary" data-ng-disabled="iscomputing" data-ng-click="request({{$image->id}},{{json_decode($image->metainfo)->laserdist}})">Compute Area</button></td>
                </tr>
            @else
                <tr>
                    <th>Compute Area</th>
                    <td><input data-ng-model="distance" id="laserdist" type="number" placeholder="Laserdistance in cm"></input></td>
                </tr>
                <tr>
                    <th></th>
                    <td><button class="btn-primary" data-ng-disabled="iscomputing" data-ng-click="request({{$image->id}})">Compute Area</button></td>
                </tr>
            @endif
        </table>
    </div>
</div>
