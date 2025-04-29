import Plugin from './components/laserpointsForm.vue';
import {VolumeShowPlugins} from './import.js';

/**
 * The plugin component to submit a new laser points job from the volume overview.
 *
 * @type {Object}
 */
if (VolumeShowPlugins) {
    VolumeShowPlugins.laserpointsForm = Plugin;
}
