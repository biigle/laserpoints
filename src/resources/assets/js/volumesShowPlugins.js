import LaserpointsForm from './components/laserpointsForm.vue';
import LaserpointsStatistics from './components/laserpointsStatistics.vue';
import {VolumeShowPlugins} from './import.js';

/**
 * The plugin components to submit a new laser points job from the volume overview
 * and to show the laser point detection statistics of the volume.
 *
 * @type {Object}
 */
if (VolumeShowPlugins) {
    VolumeShowPlugins.laserpointsForm = LaserpointsForm;
    VolumeShowPlugins.laserpointsStatistics = LaserpointsStatistics;
}
