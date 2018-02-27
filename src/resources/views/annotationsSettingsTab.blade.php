@if (\Biigle\Modules\Laserpoints\Volume::convert($volume)->hasDetectedLaserpoints())
<component :is="plugins.laserPoints" :settings="settings" inline-template>
    <div class="sidebar-tab__section">
        <h5>Laser Points Opacity (<span v-if="shown" v-text="opacity"></span><span v-else>hidden</span>)</h5>
        <input type="range" min="0" max="1" step="0.1" v-model="opacityValue">
    </div>
</component>
@endif
