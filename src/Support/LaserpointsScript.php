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
     * @param bool $decode Whether to decode the JSON output of the script
     * @throws Exception If the detection script crashed.
     *
     * @return array|string The JSON object returned by the detection script as array
     */
    public function exec($command, $decode = true)
    {
        $code = 0;
        $lines = [];
        exec($command, $lines, $code);

        if ($code === 0) {
            if (!$decode) {
                return end($lines) ?: '';
            }

            $output = $this->decodeOutput($lines);

            if (!is_null($output)) {
                return $output;
            }
        }

        // Common script errors are handled gracefully with JSON error output. If the
        // output contains no valid JSON with an 'error' property the script crashed
        // fatally.
        $message = "Fatal error with laser point detection (code {$code}).";
        Log::error($message, [
            'command' => $command,
            'output' => $lines,
        ]);

        throw new Exception($message);
    }

    /**
     * Find the JSON result in the output of a detection script.
     *
     * The scripts log to STDERR, which is merged into STDOUT, so the JSON result is not
     * necessarily the last line of the output. Search the output back to front instead
     * of relying on the position of the result.
     *
     * @param array $lines Output lines of the detection script
     *
     * @return ?array The decoded result or null if the output contains none
     */
    protected function decodeOutput(array $lines)
    {
        foreach (array_reverse($lines) as $line) {
            $output = json_decode($line, true);

            if (is_array($output) && array_key_exists('error', $output)) {
                return $output;
            }
        }

        return null;
    }
}
