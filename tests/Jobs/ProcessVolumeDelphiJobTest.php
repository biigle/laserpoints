<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use Biigle\Modules\Laserpoints\Jobs\ProcessDelphiJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessManualJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeDelphiJob;
use Biigle\Modules\Laserpoints\Support\DelphiGather;
use Biigle\Shape;
use Biigle\Tests\ImageTest;
use Biigle\Tests\LabelTest;
use Biigle\Tests\Modules\Laserpoints\ImageTest as LpImageTest;
use Biigle\Tests\VolumeTest;
use FileCache;
use Storage;
use TestCase;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;

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

        Bus::fake();

        $job = new ProcessVolumeDelphiJobStub($image->volume, 50, $label->id);
        $job->handle();


        $expect = [$image->id => '[[0,0],[0,0],[0,0]]'];
        $this->assertSame($expect, $job->points->toArray());

        Bus::assertBatched(function (PendingBatch $batch) {
            return $batch->jobs->count() === 1 && $batch->jobs[0] instanceof ProcessDelphiJob;
        });

        Bus::assertBatched(function (PendingBatch $batch) {
            return $batch->jobs->count() === 1 && $batch->jobs[0] instanceof ProcessManualJob;
        });
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
    protected function gather($points)
    {
        $this->points = $points;

        return 'abc';
    }
}
