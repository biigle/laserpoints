<?php

namespace Biigle\Modules\Laserpoints\Support;

use File;

class DetectLines extends LaserpointsScript
{
    /**
     * Execute the laser point detection script in line detection mode.
     *
     * @param array $input Map of image IDs to cached file paths.
     * @param float $distance Distance of the laser points in cm
     *
     * @return ?string The JSON object produced by the script as string
     */
    public function execute($input, $distance)
    {
        $python = config('laserpoints.python');
        $script = config('laserpoints.automatic_script');
        $tmpDir = config('laserpoints.tmp_dir');
        $workDir = $tmpDir.'/'.uniqid('biigle_laser_lines_output_');
        $inputJsonPath = $workDir.'/input.json';
        File::makeDirectory($workDir);

        try {
            File::put($inputJsonPath, json_encode($input));

            $command = "{$python} {$script} " .
                "--input-json '{$inputJsonPath}' " .
                "--output '{$workDir}' " .
                "--mode lines-only " .
                "--lines-file fitted_lines.json " .
                "--laserdistance '{$distance}' 2>&1";

            $this->exec($command, decode: false);
            $linesInfo = File::get($workDir.'/fitted_lines.json');
        } finally {
            File::deleteDirectory($workDir);
        }

        $linesJson = json_decode($linesInfo);
        if (!$linesJson || empty($linesInfo->lines)) {
            return null;
        }

        return $linesInfo;
    }
}
