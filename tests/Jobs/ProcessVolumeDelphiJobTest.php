<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use App;
use File;
use Queue;
use Cache;
use Mockery;
use TestCase;
use FileCache;
use Biigle\Shape;
use Biigle\Tests\ImageTest;
use Biigle\Tests\LabelTest;
use Biigle\Tests\VolumeTest;
use Biigle\Modules\Laserpoints\Support\DelphiGather;
use Biigle\Modules\Laserpoints\Jobs\ProcessDelphiJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessManualJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeDelphiJob;
use Biigle\Tests\Modules\Laserpoints\ImageTest as LpImageTest;

class ProcessVolumeDelphiJobTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        FileCache::fake();
    }

    public function testHandle()
    {
        $label = LabelTest::create();
        config(['laserpoints.tmp_dir' => '/tmp']);
        $image = $this->createAnnotatedImage($label);
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
        with(new ProcessVolumeDelphiJob($image->volume, 50, $label->id))->handle();
        Queue::assertPushed(ProcessDelphiJob::class, 1);
        Queue::assertPushed(ProcessManualJob::class);
    }

    public function testCacheKey()
    {
        $label = LabelTest::create();
        config(['laserpoints.tmp_dir' => '/tmp']);
        $volume = VolumeTest::create();
        for ($i = 0; $i < 2; $i++) {
            $this->createAnnotatedImage($label, $volume->id);
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

        $job = new ProcessVolumeDelphiJob($volume, 50, $label->id);
        Queue::fake();
        $job->handle();
        Queue::assertPushed(ProcessDelphiJob::class, 2);
        Queue::assertPushed(ProcessManualJob::class);
    }

    protected function createAnnotatedImage($label, $volumeId = null)
    {
        if ($volumeId) {
            $image = ImageTest::create([
                'volume_id' => $volumeId,
                'filename' => uniqid(),
            ]);
        } else {
            $image = ImageTest::create(['filename' => uniqid()]);
        }

        LpImageTest::addLaserpoints($image, $label, 3);

        return $image;
    }
}
