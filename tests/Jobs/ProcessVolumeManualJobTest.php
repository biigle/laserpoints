<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use Biigle\Image;
use Biigle\ImageAnnotation;
use Biigle\ImageAnnotationLabel;
use Biigle\Label;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeManualJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageManualJob;
use Biigle\Shape;
use Queue;
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

        (new ProcessVolumeManualJob($image->volume, $label, 30))->handle();
        Queue::assertPushed(ProcessImageManualJob::class, function ($j) use ($image, $label) {
            $this->assertEquals($image->id, $j->image->id);
            $this->assertEquals($label->id, $j->label->id);
            $this->assertEquals(30, $j->distance);
            return true;
        });

        Queue::assertPushed(ProcessImageManualJob::class, 1);
    }
}
