<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use Queue;
use Storage;
use Mockery;
use TestCase;
use Biigle\Shape;
use Biigle\Tests\ImageTest;
use Biigle\Tests\LabelTest;
use Biigle\Tests\AnnotationTest;
use Biigle\Tests\AnnotationLabelTest;
use Biigle\Modules\Laserpoints\Support\DelphiGather;
use Biigle\Modules\Laserpoints\Jobs\ProcessDelphiJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageDelphiJob;

class ProcessImageDelphiJobTest extends TestCase
{
    public function testHandle()
    {
        Storage::fake('laserpoints');

        $label = LabelTest::create();
        $image = ImageTest::create();
        for ($i = 1; $i <= 3; $i++) {
            $id = AnnotationTest::create([
                'image_id' => $image->id,
                'points' => [$i, $i],
                'shape_id' => Shape::pointId(),
            ])->id;
            AnnotationLabelTest::create([
                'annotation_id' => $id,
                'label_id' => $label->id,
            ]);
        }
        $image2 = ImageTest::create([
            'filename' => 'xyz',
            'volume_id' => $image->volume_id,
        ]);

        Queue::fake();
        $job = new ProcessImageDelphiJobStub($image2, 50, $label->id);
        $job->handle();
        Queue::assertPushed(ProcessDelphiJob::class);
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
    protected function gather($points) {
        $this->points = $points;

        return 'abc';
    }
}
