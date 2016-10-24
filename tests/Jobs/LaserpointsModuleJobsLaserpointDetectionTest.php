<?php

use Dias\Modules\Laserpoints\Jobs\ProcessChunk;
use Dias\Modules\Laserpoints\Jobs\LaserpointDetection;

class LaserpointsModuleJobsLaserpointDetectionTest extends TestCase {

    public function testHandle()
    {
        $transect = TransectTest::create();
        $images = factory(Dias\Image::class, 15)->create()
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
        $images = factory(Dias\Image::class, 15)->create()
            ->each(function ($i) use ($transect) {
                $i->filename = uniqid();
                $i->transect_id = $transect->id;
                $i->save();
            });

        Queue::shouldReceive('push')->once()->with(ProcessChunk::class);

        with(new LaserpointDetection($transect, 50, [$images->first()->id]))->handle();
    }
}
