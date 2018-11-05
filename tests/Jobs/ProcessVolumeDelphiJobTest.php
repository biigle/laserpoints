<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use App;
use File;
use Queue;
use Cache;
use Mockery;
use TestCase;
use ImageCache;
use Biigle\Shape;
use Biigle\Tests\ImageTest;
use Biigle\Tests\VolumeTest;
use Biigle\Modules\Laserpoints\Support\DelphiGather;
use Biigle\Modules\Laserpoints\Jobs\ProcessDelphiChunkJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessManualChunkJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeDelphiJob;
use Biigle\Tests\Modules\Laserpoints\ImageTest as LpImageTest;

class ProcessVolumeDelphiJobTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        ImageCache::fake();
    }

    public function testHandle()
    {
        config(['laserpoints.tmp_dir' => '/tmp']);
        $image = $this->createAnnotatedImage();
        $image2 = ImageTest::create([
            'filename' => 'xyz',
            'volume_id' => $image->volume_id,
        ]);

        $mock = Mockery::mock(DelphiGather::class);
        $mock->shouldReceive('execute')
            ->once()
            ->with(Mockery::any(), '[[0,0],[0,0],[0,0]]');
        $mock->shouldReceive('finish')->once();
        $mock->shouldReceive('getOutputPath')->once();

        App::singleton(DelphiGather::class, function () use ($mock) {
            return $mock;
        });

        Cache::shouldReceive('rememberForever')->andReturn(Shape::whereName('Point')->first());
        Cache::shouldReceive('forever')->once()->with(Mockery::any(), 1);

        Queue::fake();
        with(new ProcessVolumeDelphiJob($image->volume, 50))->handle();
        Queue::assertPushed(ProcessDelphiChunkJob::class, 1);
        Queue::assertPushed(ProcessManualChunkJob::class);
    }

    public function testHandleChunking()
    {
        config(['laserpoints.tmp_dir' => '/tmp']);
        $volume = VolumeTest::create();
        for ($i = 0; $i < 2; $i++) {
            $this->createAnnotatedImage($volume->id);
            ImageTest::create([
                'volume_id' => $volume->id,
                'filename' => uniqid(),
            ]);
        }

        $mock = Mockery::mock(DelphiGather::class);
        $mock->shouldReceive('execute')->twice();
        $mock->shouldReceive('finish')->once();
        $mock->shouldReceive('getOutputPath')->once();

        App::singleton(DelphiGather::class, function () use ($mock) {
            return $mock;
        });

        Cache::shouldReceive('rememberForever')->andReturn(Shape::whereName('Point')->first());
        Cache::shouldReceive('forever')->once()->with(Mockery::any(), 2);

        $job = new ProcessVolumeDelphiJob($volume, 50);
        $job->chunkSize = 1;
        Queue::fake();
        $job->handle();
        Queue::assertPushed(ProcessDelphiChunkJob::class, 2);
        Queue::assertPushed(ProcessManualChunkJob::class);
    }

    protected function createAnnotatedImage($volumeId = null)
    {
        if ($volumeId) {
            $image = ImageTest::create([
                'volume_id' => $volumeId,
                'filename' => uniqid(),
            ]);
        } else {
            $image = ImageTest::create(['filename' => uniqid()]);
        }

        LpImageTest::addLaserpoints($image, 3);

        return $image;
    }
}
