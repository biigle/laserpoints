@if (\Biigle\Modules\Laserpoints\Volume::convert($volume)->hasDetectedLaserpoints())
<component :is="plugins.laserPoints" :settings="settings"></component>
@endif
