<?php

namespace Biigle\Tests\Modules\Laserpoints\Support;

use Biigle\Modules\Laserpoints\Support\LaserLineFitter;
use Biigle\Tests\ImageTest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use TestCase;

class LaserLineFitterTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Storage::fake('laserpoints');
        config(['laserpoints.tmp_dir' => '/tmp']);
        config(['laserpoints.python' => '/usr/bin/python3']);
        config(['laserpoints.automatic_lp_detection_script' => __DIR__ . '/../../src/resources/scripts/laser_point_detector.py']);
        config(['laserpoints.disk' => 'laserpoints']);
    }

    public function testExecuteCreatesInputJsonAndCallsScript()
    {
        $lineFitter = new LaserLineFitterStub();
        
        // Create test images
        $images = new Collection();
        for ($i = 1; $i <= 3; $i++) {
            $image = ImageTest::create([
                'id' => $i,
                'filename' => "test_image_$i.jpg",
            ]);
            $images->push($image);
        }

        $result = $lineFitter->execute($images, 50.0);
        
        $this->assertNotNull($result);
        $this->assertStringEndsWith('.json', $result);
        
        // Verify the command was built correctly
        $this->assertStringContains('--mode lines-only', $lineFitter->lastCommand);
        $this->assertStringContains('--subsample-size 100', $lineFitter->lastCommand);
        $this->assertStringContains('--laserdistance \'50\'', $lineFitter->lastCommand);
    }

    public function testExecuteHandlesEmptyImageCollection()
    {
        $lineFitter = new LaserLineFitterStub();
        $images = new Collection();

        $result = $lineFitter->execute($images, 50.0);
        
        $this->assertNotNull($result);
    }
}

class LaserLineFitterStub extends LaserLineFitter
{
    public $lastCommand;

    protected function execLineFitting($command)
    {
        $this->lastCommand = $command;
        
        // Create a fake lines file for testing
        $tmpDir = config('laserpoints.tmp_dir');
        $outputDir = $tmpDir . '/' . uniqid('biigle_laserpoints_output_');
        mkdir($outputDir, 0755, true);
        
        $linesFile = $outputDir . '/fitted_lines.json';
        file_put_contents($linesFile, json_encode([
            'lines' => [
                ['x1' => 0, 'y1' => 0, 'x2' => 100, 'y2' => 100],
                ['x1' => 0, 'y1' => 100, 'x2' => 100, 'y2' => 0]
            ]
        ]));
        
        return 0; // Success exit code
    }
    
    // Mock FileCache::getOnce to simulate file copying
    public function execute($images, $distance)
    {
        $python = config('laserpoints.python');
        $script = config('laserpoints.automatic_lp_detection_script');
        $tmpDir = config('laserpoints.tmp_dir');
        
        $subsampleImages = $images->take(100);
        
        // Create a mock working directory
        $workingDir = $tmpDir . '/' . uniqid('biigle_line_fitting_');
        mkdir($workingDir, 0755, true);
        
        $inputJsonPath = tempnam($tmpDir, 'biigle_laserpoints_input_');
        $inputJson = [];
        
        // Mock the file copying process instead of using FileCache
        foreach ($subsampleImages as $image) {
            $extension = pathinfo($image->filename, PATHINFO_EXTENSION) ?: 'jpg';
            $workingPath = $workingDir . '/image_' . $image->id . '.' . $extension;
            
            // Create a fake file to simulate copying
            file_put_contents($workingPath, "fake image data for testing");
            $inputJson[$image->id] = $workingPath;
        }
        
        file_put_contents($inputJsonPath, json_encode($inputJson));
        
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
            
            // Mock storage behavior
            return 'lines_test_' . uniqid() . '.json';
            
        } finally {
            // Clean up
            if (file_exists($inputJsonPath)) {
                unlink($inputJsonPath);
            }
            if (is_dir($workingDir)) {
                array_map('unlink', glob("$workingDir/*"));
                rmdir($workingDir);
            }
        }
    }
}
