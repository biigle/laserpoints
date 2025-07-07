<?php

namespace Biigle\Modules\Laserpoints\Support;

use Exception;
use Biigle\FileCache\Facades\FileCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * Wrapper for the laser point line fitting script.
 */
class LaserLineFitter extends LaserpointsScript
{
    /**
     * Execute line fitting on a collection of images.
     *
     * @param Collection $images Collection of images to process for line fitting
     * @param float $distance Distance of the laser points in cm
     * @throws Exception If the line fitting script crashed.
     *
     * @return string Path to the lines file in the storage disk
     */
    public function execute(Collection $images, $distance)
    {
        $python = config('laserpoints.python');
        $script = config('laserpoints.delphi_apply_script');
        $tmpDir = config('laserpoints.tmp_dir');
        
        // Limit to subsample size for efficiency
        $subsampleImages = $images->take(100);
        
        // Create a temporary directory for our copied image files
        $workingDir = $tmpDir . '/' . uniqid('biigle_line_fitting_');
        mkdir($workingDir, 0755, true);
        
        // Create temporary input JSON file with copied image paths
        $inputJsonPath = tempnam($tmpDir, 'biigle_laserpoints_input_');
        $inputJson = [];
        
        foreach ($subsampleImages as $image) {
            try {
                // Use FileCache to get the image and copy it to our working directory
                FileCache::getOnce($image, function ($image, $cachedPath) use ($workingDir, &$inputJson) {
                    // Copy the cached file to our working directory
                    $extension = pathinfo($image->filename, PATHINFO_EXTENSION) ?: 'jpg';
                    $workingPath = $workingDir . '/image_' . $image->id . '.' . $extension;
                    
                    if (copy($cachedPath, $workingPath)) {
                        $inputJson[$image->id] = $workingPath;
                    }
                });
            } catch (Exception $e) {
                // Skip images that can't be cached/downloaded
                continue;
            }
        }
        
        // If no images could be processed, throw an exception
        if (empty($inputJson)) {
            File::deleteDirectory($workingDir);
            throw new Exception('No images could be downloaded for line fitting');
        }
        
        file_put_contents($inputJsonPath, json_encode($inputJson));
        
        // Create temporary output directory
        $outputDir = $tmpDir . '/' . uniqid('biigle_laserpoints_output_');
        mkdir($outputDir, 0755, true);
        
        $linesFileName = 'fitted_lines.json';
        
        try {
            $command = "{$python} {$script} " .
                      "--input-json '{$inputJsonPath}' " .
                      "--output '{$outputDir}' " .
                      "--mode lines-only " .
                      "--subsample-size 100 " .
                      "--lines-file '{$linesFileName}' " .
                      "--laserdistance '{$distance}' 2>&1";
            
            $result = $this->execLineFitting($command);
            
            // Check if the lines file was created
            $localLinesPath = $outputDir . '/' . $linesFileName;
            if (!File::exists($localLinesPath)) {
                throw new Exception('Line fitting failed: lines file not created');
            }
            
            // Store the lines file in the configured storage disk
            $storagePath = Storage::disk(config('laserpoints.disk'))
                ->putFileAs('', new \SplFileInfo($localLinesPath), 'lines_' . uniqid() . '.json');
            
            return $storagePath;
            
        } finally {
            // Clean up temporary files and directories
            if (File::exists($inputJsonPath)) {
                File::delete($inputJsonPath);
            }
            if (File::exists($outputDir)) {
                File::deleteDirectory($outputDir);
            }
            if (File::exists($workingDir)) {
                File::deleteDirectory($workingDir);
            }
        }
    }
    
    /**
     * Execute a line fitting command that doesn't produce JSON output.
     *
     * @param string $command Command to execute
     * @throws Exception If the line fitting script crashed.
     *
     * @return int The exit code of the command
     */
    protected function execLineFitting($command)
    {
        $code = 0;
        $lines = [];
        exec($command, $lines, $code);

        // For line fitting, we expect a successful exit code (0)
        // The script doesn't produce JSON output, just logs and files
        if ($code !== 0) {
            $message = "Fatal error with line fitting (code {$code}).";
            Log::error($message, [
                'command' => $command,
                'output' => $lines,
            ]);

            throw new Exception($message);
        }

        return $code;
    }
}
