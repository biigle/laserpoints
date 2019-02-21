<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use App;
use Queue;
use Mockery;
use TestCase;
use Biigle\Shape;
use Biigle\Tests\ImageTest;
use Biigle\Tests\LabelTest;
use Biigle\Tests\AnnotationTest;
use Biigle\Tests\AnnotationLabelTest;
use Biigle\Modules\Laserpoints\Support\DelphiGather;
use Biigle\Modules\Laserpoints\Jobs\ProcessDelphiChunkJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageDelphiJob;

class ProcessImageDelphiJobTest extends TestCase
{
    public function testHandle()
    {
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

        $mock = Mockery::mock(DelphiGather::class);
        $mock->shouldReceive('execute')
            ->once()
            ->with(Mockery::any(), '[[1,1],[2,2],[3,3]]');
        $mock->shouldReceive('finish')->once();
        $mock->shouldReceive('getOutputPath')->once();

        App::singleton(DelphiGather::class, function () use ($mock) {
            return $mock;
        });

        Queue::fake();
        with(new ProcessImageDelphiJob($image2, 50, $label->id))->handle();
        Queue::assertPushed(ProcessDelphiChunkJob::class);
    }
}
