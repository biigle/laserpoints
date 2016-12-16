<?php

namespace Dias\Tests\Modules\Laserpoints\Jobs;

use Queue;
use TestCase;
use Dias\Image;
use Dias\Tests\TransectTest;
use Dias\Modules\Laserpoints\Jobs\ProcessChunk;
use Dias\Modules\Laserpoints\Jobs\LaserpointDetection;

class LaserpointDetectionTest extends TestCase
{
    public function testHandle()
    {
        $transect = TransectTest::create();
        $images = factory(Image::class, 15)->create()
            ->each(function ($i) use ($transect) {
                $i->filename = uniqid();
                $i->transect_id = $transect->id;
                $i->save();
            });

        Queue::shouldReceive('push')->twice()->with(ProcessChunk::class);

        with(new LaserpointDetection($transect, 50))->handle();
    }

    public function testHandleOnly()
    {
        $transect = TransectTest::create();
        $images = factory(Image::class, 15)->create()
            ->each(function ($i) use ($transect) {
                $i->filename = uniqid();
                $i->transect_id = $transect->id;
                $i->save();
            });

        Queue::shouldReceive('push')->once()->with(ProcessChunk::class);

        with(new LaserpointDetection($transect, 50, [$images->first()->id]))->handle();
    }
}
