<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use Biigle\Image;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeAutomaticJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageAutomaticJob;
use Biigle\Modules\Laserpoints\Support\DetectColor;
use Biigle\Shape;
use Biigle\Volume;
use Exception;
use Illuminate\Support\Facades\Log;
use TestCase;
use Mockery;
use App;

class ProcessVolumeAutomaticJobTest extends TestCase
{
    public function testHandle()
    {
        $mock = Mockery::mock(DetectColor::class);
        $mock->shouldReceive('execute')
            ->once()
            ->with(Mockery::any(), 2)
            ->andReturn('red');

        App::singleton(DetectColor::class, function () use ($mock) {
            return $mock;
        });

        $image = Image::factory()->create();
        $image2 = Image::factory()->create();

        [$job, $batch] = (new ProcessVolumeAutomaticJob($image->volume, 30))->withFakeBatch();
        $job->handle();
        $this->assertCount(1, $batch->added);
        $j = $batch->added[0];
        $this->assertInstanceOf(ProcessImageAutomaticJob::class, $j);
        $this->assertEquals($image->id, $j->imageId);
        $this->assertEquals($image->volume_id, $j->volumeId);
        $this->assertEquals(30, $j->distance);
        $this->assertEquals('red', $j->channelMode);
        $this->assertEquals(2, $j->numLaserpoints);
        $this->assertTrue($j->batch);
    }

    public function testHandleNoImages()
    {
        Log::spy();
        $mock = Mockery::mock(DetectColor::class);
        $mock->shouldReceive('execute')->andReturn(null);
        App::singleton(DetectColor::class, fn () => $mock);
        $volume = Volume::factory()->create();

        [$job, $batch] = (new ProcessVolumeAutomaticJob($volume, 30))->withFakeBatch();
        $job->handle();
        $this->assertEmpty($batch->added);
    }

    public function testHandleColorDetectionFailure()
    {
        // Suppress the expected Log::warning() call so it doesn't pollute the log output.
        Log::spy();
        $mock = Mockery::mock(DetectColor::class);
        $mock->shouldReceive('execute')
            ->once()
            ->andThrow(new Exception('Fatal error with laser point detection.'));

        App::singleton(DetectColor::class, function () use ($mock) {
            return $mock;
        });

        $image = Image::factory()->create();

        [$job, $batch] = (new ProcessVolumeAutomaticJob($image->volume, 30))->withFakeBatch();
        $job->handle();

        // The images are still processed, using the automatic channel detection of the
        // detection script for each individual image.
        $this->assertCount(1, $batch->added);
        $j = $batch->added[0];
        $this->assertEquals($image->id, $j->imageId);
        $this->assertNull($j->channelMode);
    }
}
