<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use Queue;
use TestCase;
use Biigle\Image;
use Biigle\Tests\VolumeTest;
use Biigle\Modules\Laserpoints\Jobs\ProcessChunk;
use Biigle\Modules\Laserpoints\Jobs\LaserpointDetection;

class LaserpointDetectionTest extends TestCase
{
    public function testHandle()
    {
        $volume = VolumeTest::create();
        $images = factory(Image::class, 15)->create()
            ->each(function ($i) use ($volume) {
                $i->filename = uniqid();
                $i->volume_id = $volume->id;
                $i->save();
            });

        Queue::shouldReceive('push')->twice()->with(ProcessChunk::class);

        with(new LaserpointDetection($volume, 50))->handle();
    }

    public function testHandleOnly()
    {
        $volume = VolumeTest::create();
        $images = factory(Image::class, 15)->create()
            ->each(function ($i) use ($volume) {
                $i->filename = uniqid();
                $i->volume_id = $volume->id;
                $i->save();
            });

        Queue::shouldReceive('push')->once()->with(ProcessChunk::class);

        with(new LaserpointDetection($volume, 50, [$images->first()->id]))->handle();
    }
}
