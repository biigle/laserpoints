<?php

namespace Biigle\Modules\Laserpoints\Support;

/**
 * Wrapper for the Delphi laserpoints detection script.
 */
class DelphiApply extends LaserpointsScript
{
    /**
     * Execute a new Delphi laserpoint detection.
     *
     * @param string $gatherFile File returned from the Delphi gather script
     * @param string $imageUrl Absolute path to the image file to detect laserpoints on
     * @param float $distance Distance of the laserpoints in cm
     * @throws Exception If the detection script crashed.
     *
     * @return array The JSON object returned by the detect script as array
     */
    public function execute($gatherFile, $imageUrl, $distance)
    {
        $python = config('laserpoints.python');
        $script = config('laserpoints.delphi_apply_script');
        $command = "{$python} {$script} \"{$gatherFile}\" \"{$imageUrl}\" {$distance} 2>&1";

        return $this->exec($command);
    }
}
