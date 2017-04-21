<?php

namespace Biigle\Modules\Laserpoints\Support;

use Log;
use Exception;

/**
 * Wrapper for the laserpoints detection script.
 */
class Detect
{
    /**
     * Execute a new laserpoint detection.
     *
     * @param string $imageUrl Absolute path to the image file to detect laserpoints on
     * @param float $distance Distance of the laserpoints in cm
     * @param string $points Coordinates of all manually annotated laserpoints on the image as JSON encoded string (like `'[[100,100],[200,200]]'`)
     * @throws Exception If the detection script crashed.
     *
     * @return array The JSON object returned by the detect script as array
     */
    public function execute($imageUrl, $distance, $points)
    {
        $code = 0;
        $python = config('laserpoints.python');
        $script = config('laserpoints.detect_script');
        $lines = [];
        $command = "{$python} {$script} \"{$imageUrl}\" {$distance} \"{$points}\" 2>&1";
        $output = json_decode(exec($command, $lines, $code), true);

        // Common script errors are handled gracefully with JSON error output. If the
        // output is no valid JSON with an 'error' property the script crashed fatally.
        if ($output === null || !array_key_exists('error', $output)) {
            $message = "Fatal error with laserpoint detection (code {$code}).";
            Log::error($message, [
                'command' => $command,
                'output' => $lines,
            ]);

            throw new Exception($message);
        }

        return $output;
    }
}
