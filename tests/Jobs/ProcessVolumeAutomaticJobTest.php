<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use Biigle\Image;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeAutomaticJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageAutomaticJob;
use Biigle\Modules\Laserpoints\Support\DetectLines;
use Biigle\Shape;
use Queue;
use TestCase;
use Mockery;
use App;

class ProcessVolumeAutomaticJobTest extends TestCase
{
    public function testHandle()
    {
        $mock = Mockery::mock(DetectLines::class);
        $mock->shouldReceive('execute')
            ->once()
            ->with(Mockery::any(), 30)
            ->andReturn('lineinfo');

        App::singleton(DetectLines::class, function () use ($mock) {
            return $mock;
        });

        $image = Image::factory()->create();
        $image2 = Image::factory()->create();

        (new ProcessVolumeAutomaticJob($image->volume, 30))->handle();
        Queue::assertPushed(ProcessImageAutomaticJob::class, function ($j) use ($image) {
            $this->assertEquals($image->id, $j->image->id);
            $this->assertEquals(30, $j->distance);
            $this->assertEquals('lineinfo', $j->lineInfo);
            return true;
        });
    }

    public function testHandleWithoutLineDetection()
    {
        $image = Image::factory()->create();
        $image2 = Image::factory()->create();

        (new ProcessVolumeAutomaticJob($image->volume, 30, true))->handle();
        Queue::assertPushed(ProcessImageAutomaticJob::class, function ($j) use ($image) {
            $this->assertEquals($image->id, $j->image->id);
            $this->assertEquals(30, $j->distance);
            $this->assertNull($j->lineInfo);
            return true;
        });
    }
}
