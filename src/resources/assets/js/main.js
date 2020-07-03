import './annotationsSettingsTabPlugins';
import './laserpointsFilter';
import LaserpointsForm from './components/laserpointsForm';
import LaserpointsPanel from './laserpointsPanel';

biigle.$mount('laserpoints-panel', LaserpointsPanel);
Vue.component('laserpoints-form', LaserpointsForm);
