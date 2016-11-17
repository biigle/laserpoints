@unless ($transect->isRemote())
    <script src="{{ cachebust_asset('vendor/laserpoints/scripts/transects.js') }}"></script>
@endunless
