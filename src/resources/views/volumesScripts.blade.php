@if ($volume->isImageVolume() && !$volume->hasTiledImages())
    <script src="{{ cachebust_asset('vendor/laserpoints/scripts/main.js') }}"></script>
@endif
