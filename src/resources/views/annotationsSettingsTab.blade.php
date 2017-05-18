@if (\Biigle\Modules\Laserpoints\Volume::convert($volume)->hasDetectedLaserpoints())
<component :is="plugins.laserPoints" :settings="settings" inline-template>
    <div class="settings-tab__section" data-ng-controller="LaserpointsSettingsController">
        <label>Laser points opacity (<span v-if="shown" v-text="opacity"></span><span v-else>hidden</span>)</label>
        <input type="range" min="0" max="1" step="0.1" v-model="opacityValue">
    </div>
</component>
@endif
