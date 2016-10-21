<?php

namespace Dias\Modules\Laserpoints\Support;

use Log;
use Exception;

/**
 * Wrapper for the laserpoints detection script.
 */
class Detect
{
    /**
     * Execute a new laserpoint detection
     *
     * @param string $imageUrl Absolute path to the image file to detect laserpoints on
     * @param float $distance Distance of the laserpoints in cm
     * @param string $points Coordinates of all manually annotated laserpoints on the image as JSON encoded string (like `'[[100,100],[200,200]]'`)
     *
     * @return array The JSON object returned by the detect script as array
     */
    public function execute($imageUrl, $distance, $points)
    {
        $code = 0;
        $python = config('laserpoints.python');
        $script = config('laserpoints.script');
        $lines = [];
        $command = "{$python} {$script} \"{$imageUrl}\" {$distance} \"{$points}\" 2>&1";
        $output = exec($command, $lines, $code);

        if ($code !== 0) {
            $message = "Laserpoint detection script failed with exit code {$code}.";
            Log::error($message, [
                'command' => $command,
                'output' => $lines,
            ]);
            throw new Exception($message);
        }

        return json_decode($output, true);
    }
}
