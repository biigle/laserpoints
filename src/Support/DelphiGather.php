<?php

namespace Biigle\Modules\Laserpoints\Support;

use Exception;
use File;
use Log;

/**
 * Wrapper for the Delphi gather script.
 */
class DelphiGather
{
    /**
     * Path to the temporary ouput file of the Delphi gather script.
     *
     * @var string
     */
    protected $outputPath;

    public function __construct()
    {
        $tmpDir = config('laserpoints.tmp_dir');
        $tmpFile = uniqid('biigle_delphi_gather_output_');
        // Determine only the path of the file as it must not exist initially.
        $this->outputPath = "{$tmpDir}/{$tmpFile}";
    }

    /**
     * Execute a new Delphi preprocessing.
     *
     * @param string $path Path to the image file
     * @param string $points JSON encoded array of laser point coordinates for the image
     * @throws Exception If the script crashed.
     */
    public function execute($path, $points)
    {
        $script = config('laserpoints.delphi_gather_script');

        return $this->python("{$script} '{$path}' '{$points}' '{$this->outputPath}'");
    }

    /**
     * Finish the Delphi preprocessing after all images have been processed.
     *
     * @throws Exception If the script crashed.
     */
    public function finish()
    {
        $script = config('laserpoints.delphi_gather_finish_script');

        return $this->python("{$script} '{$this->outputPath}'");
    }

    /**
     * Get the path to the temporary ouput file of the Delphi gather script.
     *
     * @return string
     */
    public function getOutputPath()
    {
        return $this->outputPath;
    }

    /**
     * Execute a python script.
     *
     * @param string $command Script and arguments.
     * @throws Exception If the script crashed.
     */
    protected function python($command)
    {
        $code = 0;
        $python = config('laserpoints.python');
        $lines = [];
        $output = exec("{$python} {$command} 2>&1", $lines, $code);

        if ($output || $code !== 0) {
            $message = "Fatal error with Delphi gather script (code {$code}).";
            Log::error($message, [
                'command' => $command,
                'output' => $lines,
            ]);
            File::delete($this->outputPath);

            throw new Exception($message);
        }
    }
}
