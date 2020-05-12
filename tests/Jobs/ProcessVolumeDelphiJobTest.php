<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use Queue;
use Cache;
use Mockery;
use Storage;
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
        Storage::fake('laserpoints');
        config(['laserpoints.tmp_dir' => '/tmp']);
    }

    public function testHandle()
    {
        $label = LabelTest::create();
        $image = $this->createAnnotatedImage($label);
        $image2 = ImageTest::create([
            'filename' => 'xyz',
            'volume_id' => $image->volume_id,
        ]);

        Cache::shouldReceive('rememberForever')->andReturn(Shape::whereName('Point')->first());
        Cache::shouldReceive('forever')->once()->with(Mockery::any(), 1);

        Queue::fake();
        $job = new ProcessVolumeDelphiJobStub($image->volume, 50, $label->id);
        $job->handle();
        Queue::assertPushed(ProcessDelphiJob::class, 1);
        Queue::assertPushed(ProcessManualJob::class);
        $expect = [$image->id => '[[0,0],[0,0],[0,0]]'];
        $this->assertEquals($expect, $job->points->toArray());
    }

    public function testCacheKey()
    {
        $label = LabelTest::create();
        $volume = VolumeTest::create();
        for ($i = 0; $i < 2; $i++) {
            $this->createAnnotatedImage($label, $volume->id);
            ImageTest::create([
                'volume_id' => $volume->id,
                'filename' => uniqid(),
            ]);
        }

        Cache::shouldReceive('rememberForever')->andReturn(Shape::whereName('Point')->first());
        Cache::shouldReceive('forever')->once()->with(Mockery::any(), 2);

        Queue::fake();
        $job = new ProcessVolumeDelphiJobStub($volume, 50, $label->id);
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

class ProcessVolumeDelphiJobStub extends ProcessVolumeDelphiJob
{

    protected function gather($points) {
        $this->points = $points;

        return 'abc';
    }
}
