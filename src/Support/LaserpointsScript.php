<?php

namespace Biigle\Modules\Laserpoints\Support;

use Exception;
use Illuminate\Support\Facades\Process;
use Log;

class LaserpointsScript
{
    /**
     * Execute a laser point detection command.
     *
     * @param array $command Command to execute as array of the executable and its arguments
     * @param bool $decode Whether to decode the JSON output of the script
     * @throws Exception If the detection script crashed.
     *
     * @return array|string The JSON object returned by the detection script as array
     */
    public function exec(array $command, $decode = true)
    {
        // The default timeout of 60 s is too short for the color detection on many
        // images. The timeout of the queued job applies instead.
        $result = Process::forever()->run($command);
        // The scripts log to STDERR so STDOUT contains only the result.
        $output = trim($result->output());

        if ($result->successful()) {
            if (!$decode) {
                return $output;
            }

            $decoded = json_decode($output, true);

            // Common script errors are handled gracefully with JSON error output. If the
            // output is no valid JSON with an 'error' property the script crashed
            // fatally.
            if (is_array($decoded) && array_key_exists('error', $decoded)) {
                return $decoded;
            }
        }

        $message = "Fatal error with laser point detection (code {$result->exitCode()}).";
        Log::error($message, [
            'command' => $command,
            'output' => $output,
            'error_output' => $result->errorOutput(),
        ]);

        throw new Exception($message);
    }
}
