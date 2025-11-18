<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use App;
use Biigle\Label;
use Biigle\Shape;
use Biigle\ImageAnnotation;
use Biigle\ImageAnnotationLabel;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageManualJob;
use Biigle\Modules\Laserpoints\Support\DetectManual;
use Biigle\Tests\ImageTest;
use Exception;
use Mockery;
use TestCase;

class ProcessImageManualJobTest extends TestCase
{
    protected $image;
    protected $label;

    public function setUp(): void
    {
        parent::setUp();
        $this->image = Image::convert(ImageTest::create(['attrs' => ['a' => 1]]));
        $this->label = Label::factory()->create();
        ImageAnnotationLabel::factory()->create([
            'label_id' => $this->label->id,
            'annotation_id' => ImageAnnotation::factory()->create([
                'points' => [100, 100],
                'shape_id' => Shape::pointId(),
                'image_id' => $this->image->id,
            ])->id,
        ]);
        ImageAnnotationLabel::factory()->create([
            'label_id' => $this->label->id,
            'annotation_id' => ImageAnnotation::factory()->create([
                'points' => [100, 100],
                'shape_id' => Shape::pointId(),
                'image_id' => $this->image->id,
            ])->id,
        ]);
        ImageAnnotationLabel::factory()->create([
            'label_id' => $this->label->id,
            'annotation_id' => ImageAnnotation::factory()->create([
                'points' => [100, 100],
                'shape_id' => Shape::pointId(),
                'image_id' => $this->image->id,
            ])->id,
        ]);
    }

    public function testHandle()
    {
        $mock = Mockery::mock(DetectManual::class);
        $mock->shouldReceive('execute')
            ->once()
            ->with(Mockery::any(), 30, '[[100,100],[100,100],[100,100]]')
            ->andReturn([
                'error' => false,
                'area' => 100,
                'count' => 3,
                'method' => 'manual',
                'points' => [[100, 100], [100, 100], [100, 100]],
            ]);

        App::singleton(DetectManual::class, function () use ($mock) {
            return $mock;
        });

        with(new ProcessImageManualJob($this->image, $this->label, 30))->handle();

        $expect = [
            'error' => false,
            'area' => 100,
            'count' => 3,
            'method' => 'manual',
            'points' => [[100, 100], [100, 100], [100, 100]],
            'distance' => 30,
        ];
        $this->assertSame($expect, $this->image->fresh()->laserpoints);
        // Previously set attributes should not be lost.
        $this->assertSame(1, $this->image->fresh()->attrs['a']);
    }

    public function testHandleGracefulError()
    {
        $mock = Mockery::mock(DetectManual::class);
        $mock->shouldReceive('execute')
            ->once()
            ->andReturn([
                'error' => true,
                'message' => 'Some expected error occurred.',
            ]);

        App::singleton(DetectManual::class, function () use ($mock) {
            return $mock;
        });

        with(new ProcessImageManualJob($this->image, $this->label, 30))->handle();

        $expect = [
            'error' => true,
            'message' => 'Some expected error occurred.',
            'distance' => 30,
        ];

        $this->assertSame($expect, $this->image->fresh()->laserpoints);
    }

    public function testHandleFatalError()
    {
        // previous laserpoint detection results should be removed
        $this->image->laserpoints = [
            'area' => 100,
            'count' => 3,
            'method' => 'manual',
            'points' => [[100, 100], [100, 100], [100, 100]],
            'error' => false,
            'distance' => 30,
        ];
        $this->image->save();

        $mock = Mockery::mock(DetectManual::class);
        $mock->shouldReceive('execute')
            ->once()
            ->andThrow(new Exception('Fatal error message.'));

        App::singleton(DetectManual::class, function () use ($mock) {
            return $mock;
        });

        with(new ProcessImageManualJob($this->image, $this->label, 30))->handle();

        $expect = [
            'error' => true,
            'message' => 'Fatal error message.',
            'distance' => 30,
        ];

        $this->assertSame($expect, $this->image->fresh()->laserpoints);
    }
}
