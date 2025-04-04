import './annotationsSettingsTabPlugins.js';
import './laserpointsFilter.js';
import LaserpointsForm from './components/laserpointsForm.vue';
import LaserpointsPanel from './laserpointsPanel.vue';

biigle.$mount('laserpoints-panel', LaserpointsPanel);
Vue.component('laserpoints-form', LaserpointsForm);
