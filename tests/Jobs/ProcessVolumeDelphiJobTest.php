<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use App;
use File;
use Queue;
use Mockery;
use TestCase;
use Biigle\Label;
use Biigle\Shape;
use Biigle\Tests\ImageTest;
use Biigle\Tests\LabelTest;
use Biigle\Tests\VolumeTest;
use Biigle\Tests\AnnotationTest;
use Biigle\Tests\AnnotationLabelTest;
use Biigle\Modules\Laserpoints\Support\DelphiGather;
use Biigle\Modules\Laserpoints\Jobs\ProcessDelphiChunkJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessManualChunkJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeDelphiJob;

class ProcessVolumeDelphiJobTest extends TestCase
{
    public function testHandle()
    {
        $image = $this->createAnnotatedImage();
        $image2 = ImageTest::create([
            'filename' => 'xyz',
            'volume_id' => $image->volume_id,
        ]);

        $mock = Mockery::mock(DelphiGather::class);
        $mock->shouldReceive('execute')
            ->once()
            ->with($image->volume->url, [$image->filename], [[[1, 1], [2, 2], [3, 3]]]);

        App::singleton(DelphiGather::class, function () use ($mock) {
            return $mock;
        });

        File::shouldReceive('put')->once()->with(Mockery::on(function ($path) {
            // Use this validator function to delete the countFile after each test.
            unlink($path);

            return !!$path;
        }), '[0]');

        Queue::shouldReceive('push')->once()->with(ProcessDelphiChunkJob::class);
        Queue::shouldReceive('push')->once()->with(ProcessManualChunkJob::class);
        with(new ProcessVolumeDelphiJob($image->volume, 50))->handle();
    }

    public function testHandleChunking()
    {
        $volume = VolumeTest::create();
        for ($i = 0; $i < 2; $i++) {
            $this->createAnnotatedImage($volume->id);
            ImageTest::create([
                'volume_id' => $volume->id,
                'filename' => uniqid(),
            ]);
        }

        $mock = Mockery::mock(DelphiGather::class);
        $mock->shouldReceive('execute')
            ->once();

        App::singleton(DelphiGather::class, function () use ($mock) {
            return $mock;
        });

        File::shouldReceive('put')->once()->with(Mockery::on(function ($path) {
            // Use this validator function to delete the countFile after each test.
            unlink($path);

            return !!$path;
        }), '[0,1]');

        Queue::shouldReceive('push')->twice()->with(ProcessDelphiChunkJob::class);
        Queue::shouldReceive('push')->twice()->with(ProcessManualChunkJob::class);
        $job = new ProcessVolumeDelphiJob($volume, 50);
        $job->chunkSize = 1;
        $job->handle();
    }

    protected function createAnnotatedImage($volumeId = null)
    {
        $labelId = config('laserpoints.label_id');
        if (!Label::where('id', $labelId)->exists()) {
            LabelTest::create(['id' => $labelId]);
        }
        if ($volumeId) {
            $image = ImageTest::create([
                'volume_id' => $volumeId,
                'filename' => uniqid(),
            ]);
        } else {
            $image = ImageTest::create(['filename' => uniqid()]);
        }

        for ($i = 1; $i <= 3; $i++) {
            $id = AnnotationTest::create([
                'image_id' => $image->id,
                'points' => [$i, $i],
                'shape_id' => Shape::$pointId,
            ])->id;
            AnnotationLabelTest::create([
                'annotation_id' => $id,
                'label_id' => $labelId,
            ]);
        }

        return $image;
    }
}
