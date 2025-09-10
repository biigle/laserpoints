<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeLinesJob;
use Biigle\Modules\Laserpoints\Support\LaserLineFitter;
use Biigle\Tests\ImageTest;
use Biigle\Tests\LabelTest;
use Biigle\Tests\VolumeTest;
use Illuminate\Support\Facades\Cache;
use Mockery;
use TestCase;

class ProcessVolumeLinesJobTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function testHandle()
    {
        $volume = VolumeTest::create();
        $label = LabelTest::create();
        
        // Create some test images for the volume
        $images = collect();
        for ($i = 0; $i < 5; $i++) {
            $images->push(ImageTest::create([
                'volume_id' => $volume->id,
                'filename' => "test_image_$i.jpg",
            ]));
        }

        // Mock the LaserLineFitter
        $mockLineFitter = Mockery::mock(LaserLineFitter::class);
        $mockLineFitter->shouldReceive('execute')
            ->once()
            ->with(Mockery::type('Illuminate\Database\Eloquent\Collection'), 50.0)
            ->andReturn('lines_file_path.json');

        $this->app->instance(LaserLineFitter::class, $mockLineFitter);

        $job = new ProcessVolumeLinesJob($volume, 50.0, $label->id);
        $job->handle();

        // Verify that the lines file path was cached
        $cacheKey = "laserpoint_lines_volume_{$volume->id}";
        $this->assertEquals('lines_file_path.json', Cache::get($cacheKey));
    }

    public function testHandleWithFewerThan100Images()
    {
        $volume = VolumeTest::create();
        $label = LabelTest::create();
        
        // Create only 2 images (less than 100)
        for ($i = 0; $i < 2; $i++) {
            ImageTest::create([
                'volume_id' => $volume->id,
                'filename' => "test_image_$i.jpg",
            ]);
        }

        // Mock the LaserLineFitter
        $mockLineFitter = Mockery::mock(LaserLineFitter::class);
        $mockLineFitter->shouldReceive('execute')
            ->once()
            ->andReturn('lines_file_path.json');

        $this->app->instance(LaserLineFitter::class, $mockLineFitter);

        $job = new ProcessVolumeLinesJob($volume, 50.0, $label->id);
        
        // This should work without errors, just log a warning
        $job->handle();

        // Verify that the lines file path was still cached
        $cacheKey = "laserpoint_lines_volume_{$volume->id}";
        $this->assertEquals('lines_file_path.json', Cache::get($cacheKey));
    }
}
