<?php

namespace Biigle\Modules\Laserpoints\Support;

use File;

class DetectAutomatic extends LaserpointsScript
{
    /**
     * Execute a new automatic laser point detection without line detection.
     *
     * @param string $imagePath Absolute path to the image file to detect laserpoints on
     * @param float $distance Distance of the laser points in cm
     * @param ?string $lineInfo JSON string from the line detection
     *
     * @return array The JSON object returned by the detect script as array
     */
    public function execute($imagePath, $distance, $lineInfo = null)
    {
        $python = config('laserpoints.python');
        $script = config('laserpoints.automatic_script');

        if (!$lineInfo) {
            $command = "{$python} {$script} " .
                "--input '{$imagePath}' " .
                "--laserdistance '{$distance}' " .
                "--mode biigle_mode 2>&1";

            return $this->exec($command);
        }

        $tmpDir = config('laserpoints.tmp_dir');
        $lineInfoPath = tempnam($tmpDir, 'biigle_lines_');
        File::put($lineInfoPath, $lineInfo);

        try {
            $command = "{$python} {$script} " .
                "--input '{$imagePath}' " .
                "--lines-file '{$lineInfoPath}' " .
                "--laserdistance '{$distance}' " .
                "--mode biigle_mode_with_lines 2>&1";

            return $this->exec($command);

        } finally {
            File::delete($lineInfoPath);
        }
    }
}
