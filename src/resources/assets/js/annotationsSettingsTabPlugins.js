import Plugin from './components/annotationsSettingsTabPlugin';
import {SettingsTabPlugins} from './import';

/**
 * The plugin component to change the settings for the laser points in the annotation
 * tool.
 *
 * @type {Object}
 */
if (SettingsTabPlugins) {
    SettingsTabPlugins.laserPoints = Plugin;
}
