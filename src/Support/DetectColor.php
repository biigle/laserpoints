<?php

namespace Biigle\Modules\Laserpoints\Support;

use File;

class DetectColor extends LaserpointsScript
{
    /**
     * Execute the laser point color detection on a subset of images.
     *
     * @param array $input Map of image IDs to cached file paths.
     * @param int $numLaserpoints Number of laser points to search for
     *
     * @return ?string The detected channel mode (red/green/blue/gray)
     */
    public function execute($input, $numLaserpoints = 2)
    {
        $python = config('laserpoints.python');
        $script = config('laserpoints.automatic_script');
        $tmpDir = config('laserpoints.tmp_dir');
        $workDir = $tmpDir.'/'.uniqid('biigle_laser_color_output_');
        $inputJsonPath = $workDir.'/input.json';
        $colorFile = 'color.txt';
        File::makeDirectory($workDir);

        try {
            File::put($inputJsonPath, json_encode($input));

            $command = "{$python} {$script} " .
                "--input-json '{$inputJsonPath}' " .
                "--output '{$workDir}' " .
                "--mode lpcolor " .
                "--color-file '{$colorFile}' " .
                "--num-laserpoints '{$numLaserpoints}' 2>&1";

            $this->exec($command, decode: false);

            $color = trim(File::get($workDir.'/'.$colorFile));
        } finally {
            File::deleteDirectory($workDir);
        }

        $color = strtolower($color);
        if (!in_array($color, ['red', 'green', 'blue', 'gray'], true)) {
            return null;
        }

        return $color;
    }
}
