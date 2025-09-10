<?php

namespace Biigle\Modules\Laserpoints\Support;

/**
 * Wrapper for the Delphi laser points detection script.
 */
class DelphiApply extends LaserpointsScript
{
    /**
     * Execute a new Delphi laser point detection.
     *
     * @param string $gatherFile File returned from the Delphi gather script (deprecated - now used as lines file path)
     * @param string $imageUrl Absolute path to the image file to detect laserpoints on
     * @param float $distance Distance of the laser points in cm
     * @throws Exception If the detection script crashed.
     *
     * @return array The JSON object returned by the detect script as array
     */
    public function execute($gatherFile, $imageUrl, $distance)
    {
        $python = config('laserpoints.python');
        $script = config('laserpoints.automatic_lp_detection_script');
        
        // Check if we have a lines file (new line-based approach)
        if ($gatherFile && \Illuminate\Support\Facades\Storage::disk(config('laserpoints.disk'))->exists($gatherFile)) {
            // Download the lines file to a temporary location
            $tmpDir = config('laserpoints.tmp_dir');
            $localLinesPath = tempnam($tmpDir, 'biigle_lines_');
            $stream = \Illuminate\Support\Facades\Storage::disk(config('laserpoints.disk'))->readStream($gatherFile);
            \Illuminate\Support\Facades\File::put($localLinesPath, $stream);
            
            try {
                $command = "{$python} {$script} " .
                          "--input '{$imageUrl}' " .
                          "--lines-file '{$localLinesPath}' " .
                          "--mode biigle_mode_with_lines " .
                          "--laserdistance '{$distance}' 2>&1";
                
                return $this->exec($command);
            } finally {
                \Illuminate\Support\Facades\File::delete($localLinesPath);
            }
        } else {
            // Fallback to the old biigle_mode without lines
            $command = "{$python} {$script} " .
                      "--input '{$imageUrl}' " .
                      "--laserdistance '{$distance}' " .
                      "--mode biigle_mode 2>&1";
            
            return $this->exec($command);
        }
    }
}
