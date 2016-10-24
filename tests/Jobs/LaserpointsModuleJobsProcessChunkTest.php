<?php

use Dias\Shape;
use Dias\Modules\Laserpoints\Image;
use Dias\Modules\Laserpoints\Support\Detect;
use Dias\Modules\Laserpoints\Jobs\ProcessChunk;

class LaserpointsModuleJobsProcessChunkTest extends TestCase {

    public function testHandleHeuristic()
    {
        $image = Image::convert(ImageTest::create());

        $mock = Mockery::mock(Detect::class);
        $mock->shouldReceive('execute')
            ->once()
            ->with("{$image->transect->url}/{$image->filename}", 20, '[]')
            ->andReturn([
                'error' => false,
                'area' => 100,
                'px' => 50000,
                'count' => 3,
                'method' => 'heuristic',
                'points' => [[100, 200], [300, 400], [500, 600]],
            ]);

        App::singleton(Detect::class, function () use ($mock) {
            return $mock;
        });

        with(new ProcessChunk($image->transect->url, [$image->id], 20))->handle();

        $expect = [
            'area' => 100,
            'px' => 50000,
            'count' => 3,
            'method' => 'heuristic',
            'points' => [[100, 200], [300, 400], [500, 600]],
            'error' => false,
            'distance' => 20,
        ];

        $this->assertEquals($expect, $image->fresh()->laserpoints);

    }

    public function testHandleManual()
    {
        $image = Image::convert(ImageTest::create());
        $label = LabelTest::create();
        config(['laserpoints.label_id' => $label->id]);

        for ($i = 1; $i <= 3; $i++) {
            $id = AnnotationTest::create([
                'image_id' => $image->id,
                'points' => [100 * $i, 100 * $i],
                'shape_id' => Shape::$pointId,
            ])->id;
            AnnotationLabelTest::create([
                'annotation_id' => $id,
                'label_id' => $label->id,
            ]);
        }

        $mock = Mockery::mock(Detect::class);
        $mock->shouldReceive('execute')
            ->once()
            ->with(
                "{$image->transect->url}/{$image->filename}",
                30,
                '[[100,100],[200,200],[300,300]]'
            )
            ->andReturn([
                'error' => false,
                'area' => 100,
                'px' => 50000,
                'count' => 3,
                'method' => 'manual',
                'points' => [[100, 100], [200, 200], [300, 300]],
            ]);

        App::singleton(Detect::class, function () use ($mock) {
            return $mock;
        });


        with(new ProcessChunk($image->transect->url, [$image->id], 30))->handle();

        $expect = [
            'area' => 100,
            'px' => 50000,
            'count' => 3,
            'method' => 'manual',
            'points' => [[100, 100], [200, 200], [300, 300]],
            'error' => false,
            'distance' => 30,
        ];

        $this->assertEquals($expect, $image->fresh()->laserpoints);

    }

    public function testHandleGracefulError()
    {
        $image = Image::convert(ImageTest::create());

        $mock = Mockery::mock(Detect::class);
        $mock->shouldReceive('execute')
            ->once()
            ->andReturn([
                'error' => true,
                'message' => 'Some expected error occurred.',
            ]);

        App::singleton(Detect::class, function () use ($mock) {
            return $mock;
        });

        with(new ProcessChunk($image->transect->url, [$image->id], 30))->handle();

        $expect = [
            'error' => true,
            'message' => 'Some expected error occurred.',
            'distance' => 30,
        ];

        $this->assertEquals($expect, $image->fresh()->laserpoints);
    }

    public function testHandleFatalError()
    {
        $image = Image::convert(ImageTest::create());

        // previous laserpoint detection results should be removed
        $image->laserpoints = [
            'area' => 100,
            'px' => 50000,
            'count' => 3,
            'method' => 'manual',
            'points' => [[100, 100], [200, 200], [300, 300]],
            'error' => false,
            'distance' => 30,
        ];
        $image->save();

        $mock = Mockery::mock(Detect::class);
        $mock->shouldReceive('execute')
            ->once()
            ->andThrow(new Exception('Fatal error message.'));

        App::singleton(Detect::class, function () use ($mock) {
            return $mock;
        });

        with(new ProcessChunk($image->transect->url, [$image->id], 30))->handle();

        $expect = [
            'error' => true,
            'message' => 'Fatal error message.',
            'distance' => 30,
        ];

        $this->assertEquals($expect, $image->fresh()->laserpoints);
    }
}
