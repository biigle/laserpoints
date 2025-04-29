@if ($volume->isImageVolume() && !$volume->hasTiledImages())
    {{vite_hot(base_path('vendor/biigle/laserpoints/hot'), ['src/resources/assets/js/main.js'], 'vendor/laserpoints')}}

@endif
