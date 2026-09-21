<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use Biigle\Image;
use Biigle\ImageAnnotation;
use Biigle\ImageAnnotationLabel;
use Biigle\Label;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeManualJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageManualJob;
use Biigle\Shape;
use Biigle\Volume;
use TestCase;

class ProcessVolumeManualJobTest extends TestCase
{
    public function testHandle()
    {
        $image = Image::factory()->create();
        $label = Label::factory()->create();
        ImageAnnotationLabel::factory()->create([
            'label_id' => $label->id,
            'annotation_id' => ImageAnnotation::factory()->create([
                'points' => [100, 100],
                'shape_id' => Shape::pointId(),
                'image_id' => $image->id,
            ])->id,
        ]);

        ImageAnnotationLabel::factory()->create([
            'label_id' => $label->id,
            'annotation_id' => ImageAnnotation::factory()->create([
                'points' => [200, 200],
                'shape_id' => Shape::pointId(),
                'image_id' => $image->id,
            ])->id,
        ]);

        // Different label
        $image2 = Image::factory()->create([
            'volume_id' => $image->volume_id,
            'filename' => '123.jpg',
        ]);
        ImageAnnotationLabel::factory()->create([
            'annotation_id' => ImageAnnotation::factory()->create([
                'points' => [100, 100],
                'shape_id' => Shape::pointId(),
                'image_id' => $image->id,
            ])->id,
        ]);

        // Different volume
        ImageAnnotationLabel::factory()->create([
            'label_id' => $label->id,
            'annotation_id' => ImageAnnotation::factory()->create([
                'points' => [100, 100],
                'shape_id' => Shape::pointId(),
            ])->id,
        ]);

        [$job, $batch] = (new ProcessVolumeManualJob($image->volume, $label, 30))->withFakeBatch();
        $job->handle();
        $this->assertCount(1, $batch->added);
        $j = $batch->added[0];
        $this->assertInstanceOf(ProcessImageManualJob::class, $j);
        $this->assertEquals($image->id, $j->imageId);
        $this->assertEquals($image->volume_id, $j->volumeId);
        $this->assertEquals($label->id, $j->label->id);
        $this->assertEquals(30, $j->distance);
        $this->assertTrue($j->batch);
    }

    public function testHandleNoImages()
    {
        $volume = Volume::factory()->create();
        $label = Label::factory()->create();

        [$job, $batch] = (new ProcessVolumeManualJob($volume, $label, 30))->withFakeBatch();
        $job->handle();
        $this->assertEmpty($batch->added);
    }
}
