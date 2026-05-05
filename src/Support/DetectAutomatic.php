<?php

namespace Biigle\Modules\Laserpoints\Support;

class DetectAutomatic extends LaserpointsScript
{
    /**
     * Execute an automatic laser point detection run.
     *
     * @param string $imagePath Absolute path to the image file to detect laserpoints on
     * @param float $distance Distance of the laser points in cm
     * @param ?string $channelMode Channel mode (red/green/blue/gray) or null for auto-detection
     * @param int $numLaserpoints Number of laser points to search for
     *
     * @return array The JSON object returned by the detect script as array
     */
    public function execute($imagePath, $distance, $channelMode = null, $numLaserpoints = 2)
    {
        $python = config('laserpoints.python');
        $script = config('laserpoints.automatic_script');
        $channelMode = $channelMode ? strtolower($channelMode) : 'auto';

        $command = "{$python} {$script} " .
            "--input '{$imagePath}' " .
            "--laserdistance '{$distance}' " .
            "--num-laserpoints '{$numLaserpoints}' " .
            "--channel '{$channelMode}' " .
            "--mode biigle_mode 2>&1";

        return $this->exec($command);
    }
}
