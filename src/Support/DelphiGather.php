<?php

namespace Biigle\Modules\Laserpoints\Support;

use Log;
use File;
use Exception;

/**
 * Wrapper for the Delphi gather script.
 */
class DelphiGather
{
    /**
     * Execute a new Delphi preprocessing.
     *
     * @param string $volumeUrl URL of the volume images
     * @param array $filenames Array of image filenames
     * @param array $points Array of laserpoint coordinates for each image
     * @throws Exception If the script crashed.
     *
     * @return string Path to the temporary output file of the script
     */
    public function execute($volumeUrl, $filenames, $points)
    {
        $code = 0;
        $python = config('laserpoints.python');
        $script = config('laserpoints.delphi_gather_script');
        $inputFile = tempnam(sys_get_temp_dir(), 'biigle_delphi_gather_input');
        $outputFile = tempnam(sys_get_temp_dir(), 'biigle_delphi_gather_output');
        $inputContent = [
            'filePrefix' => $volumeUrl,
            'manLaserpoints' => $points,
            'manLaserpointFiles' => $filenames,
            'tmpFile' => $outputFile,
        ];
        $lines = [];
        $command = "{$python} {$script} \"{$inputFile}\" 2>&1";
        File::put($inputFile, json_encode($inputContent));
        $output = exec($command, $lines, $code);
        File::delete($inputFile);

        if ($output || $code !== 0) {
            $message = "Fatal error with Delphi gather script (code {$code}).";
            Log::error($message, [
                'command' => $command,
                'input' => $inputContent,
                'output' => $lines,
            ]);
            File::delete($outputFile);

            throw new Exception($message);
        }

        return $outputFile;
    }
}
