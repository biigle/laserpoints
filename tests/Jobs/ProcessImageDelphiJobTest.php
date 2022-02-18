<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use Biigle\Modules\Laserpoints\Jobs\ProcessDelphiJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageDelphiJob;
use Biigle\Shape;
use Biigle\Tests\ImageAnnotationLabelTest;
use Biigle\Tests\ImageAnnotationTest;
use Biigle\Tests\ImageTest;
use Biigle\Tests\LabelTest;
use Storage;
use TestCase;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;

class ProcessImageDelphiJobTest extends TestCase
{
    public function testHandle()
    {
        Storage::fake('laserpoints');

        $label = LabelTest::create();
        $image = ImageTest::create();
        for ($i = 1; $i <= 3; $i++) {
            $id = ImageAnnotationTest::create([
                'image_id' => $image->id,
                'points' => [$i, $i],
                'shape_id' => Shape::pointId(),
            ])->id;
            ImageAnnotationLabelTest::create([
                'annotation_id' => $id,
                'label_id' => $label->id,
            ]);
        }
        $image2 = ImageTest::create([
            'filename' => 'xyz',
            'volume_id' => $image->volume_id,
        ]);

        Bus::fake();
        $job = new ProcessImageDelphiJobStub($image2, 50, $label->id);
        $job->handle();

        Bus::assertBatched(function (PendingBatch $batch) {
            return $batch->jobs->count() === 1 && $batch->jobs[0] instanceof ProcessDelphiJob;
        });

        $points = $job->points->toArray();
        $this->assertArrayHasKey($image->id, $points);
        $points = $points[$image->id];
        $this->assertStringContainsString('[1,1]', $points);
        $this->assertStringContainsString('[2,2]', $points);
        $this->assertStringContainsString('[3,3]', $points);
    }
}

class ProcessImageDelphiJobStub extends ProcessImageDelphiJob
{
    protected function gather($points)
    {
        $this->points = $points;

        return 'abc';
    }
}
