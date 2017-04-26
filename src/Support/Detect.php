<?php

namespace Biigle\Modules\Laserpoints\Support;

/**
 * Wrapper for the manual laser points detection script.
 */
class Detect extends LaserpointsScript
{
    /**
     * Execute a new manual laser point detection.
     *
     * @param string $imageUrl Absolute path to the image file to detect laserpoints on
     * @param float $distance Distance of the laser points in cm
     * @param string $points Coordinates of all manually annotated laser points on the image as JSON encoded string (like `'[[100,100],[200,200]]'`)
     * @throws Exception If the detection script crashed.
     *
     * @return array The JSON object returned by the detect script as array
     */
    public function execute($imageUrl, $distance, $points)
    {
        $python = config('laserpoints.python');
        $script = config('laserpoints.detect_script');
        $command = "{$python} {$script} \"{$imageUrl}\" {$distance} \"{$points}\" 2>&1";

        return $this->exec($command);
    }
}
