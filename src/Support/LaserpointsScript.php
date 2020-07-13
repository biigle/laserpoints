<?php

namespace Biigle\Modules\Laserpoints\Support;

use Exception;
use Log;

class LaserpointsScript
{
    /**
     * Execute a laser point detection command.
     *
     * @param string $command Command to execute
     * @throws Exception If the detection script crashed.
     *
     * @return array The JSON object returned by the detection script as array
     */
    public function exec($command)
    {
        $code = 0;
        $lines = [];
        $output = json_decode(exec($command, $lines, $code), true);

        // Common script errors are handled gracefully with JSON error output. If the
        // output is no valid JSON with an 'error' property the script crashed fatally.
        if ($output === null || !array_key_exists('error', $output)) {
            $message = "Fatal error with laser point detection (code {$code}).";
            Log::error($message, [
                'command' => $command,
                'output' => $lines,
            ]);

            throw new Exception($message);
        }

        return $output;
    }
}
