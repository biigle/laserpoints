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
use Biigle\Modules\Laserpoints\Support\DetectionLock;
use Biigle\Tests\ImageTest;
use Cache;
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
        $this->image = Image::convert(ImageTest::create([
            'attrs' => ['a' => 1, 'width' => 1000, 'height' => 800],
        ]));
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
            ->with(1000, 800, 30, [[100, 100], [100, 100], [100, 100]])
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

    public function testHandleDuplicateLabels()
    {
        // The same label can be attached to the same annotation by multiple users. The
        // points of such an annotation must be collected only once, else the computed
        // image area would be wrong.
        ImageAnnotationLabel::factory()->create([
            'label_id' => $this->label->id,
            'annotation_id' => $this->image->annotations()->first()->id,
        ]);

        $mock = Mockery::mock(DetectManual::class);
        $mock->shouldReceive('execute')
            ->once()
            ->with(1000, 800, 30, [[100, 100], [100, 100], [100, 100]])
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

    public function testHandleReleasesImageLock()
    {
        $this->mockDetection();
        DetectionLock::acquireImage($this->image->volume_id, $this->image->id);

        with(new ProcessImageManualJob($this->image, $this->label, 30))->handle();
        $this->assertFalse(Cache::has(DetectionLock::key($this->image->volume_id)));
    }

    public function testFailedReleasesImageLock()
    {
        DetectionLock::acquireImage($this->image->volume_id, $this->image->id);

        with(new ProcessImageManualJob($this->image, $this->label, 30))->failed(new Exception);
        $this->assertFalse(Cache::has(DetectionLock::key($this->image->volume_id)));
    }

    public function testHandleBatchKeepsVolumeLock()
    {
        $this->mockDetection();
        DetectionLock::acquireVolume($this->image->volume_id);

        with(new ProcessImageManualJob($this->image, $this->label, 30, batch: true))->handle();
        $this->assertTrue(Cache::has(DetectionLock::key($this->image->volume_id)));
    }

    public function testHandleImageDeleted()
    {
        $mock = Mockery::mock(DetectManual::class);
        $mock->shouldReceive('execute')->never();
        App::singleton(DetectManual::class, fn () => $mock);
        DetectionLock::acquireImage($this->image->volume_id, $this->image->id);
        $job = new ProcessImageManualJob($this->image, $this->label, 30);
        $this->image->delete();

        $job->handle();
        $this->assertFalse(Cache::has(DetectionLock::key($this->image->volume_id)));
    }

    public function testHandleImageDeletedBatch()
    {
        $mock = Mockery::mock(DetectManual::class);
        $mock->shouldReceive('execute')->never();
        App::singleton(DetectManual::class, fn () => $mock);
        DetectionLock::acquireVolume($this->image->volume_id);
        $job = new ProcessImageManualJob($this->image, $this->label, 30, batch: true);
        $this->image->delete();

        $job->handle();
        $this->assertTrue(Cache::has(DetectionLock::key($this->image->volume_id)));
    }

    protected function mockDetection()
    {
        $mock = Mockery::mock(DetectManual::class);
        $mock->shouldReceive('execute')->andReturn(['error' => false]);
        App::singleton(DetectManual::class, fn () => $mock);
    }
}
