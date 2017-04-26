<h4>Laser points</h4>

<div class="settings-foldout__item" data-ng-controller="LaserpointsSettingsController">
    <label title="Show automatically detected laserpoints">Opacity <span class="ng-cloak" data-ng-switch="laserpoint_opacity!=='0'">(<span data-ng-switch-when="true" data-ng-bind="laserpoint_opacity | number:2"></span><span data-ng-switch-default="">hidden</span>)</span></label>
    <input type="range" min="0" max="1" step="0.01" data-ng-model="laserpoint_opacity">
</div>
