<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use Biigle\Image;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeAutomaticJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageAutomaticJob;
use Biigle\Modules\Laserpoints\Support\DetectColor;
use Biigle\Shape;
use Queue;
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

        (new ProcessVolumeAutomaticJob($image->volume, 30))->handle();
        Queue::assertPushed(ProcessImageAutomaticJob::class, function ($j) use ($image) {
            $this->assertEquals($image->id, $j->image->id);
            $this->assertEquals(30, $j->distance);
            $this->assertEquals('red', $j->channelMode);
            $this->assertEquals(2, $j->numLaserpoints);
            return true;
        });
    }
}
