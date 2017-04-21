<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use App;
use Mockery;
use TestCase;
use Exception;
use Biigle\Shape;
use Biigle\Tests\ImageTest;
use Biigle\Tests\LabelTest;
use Biigle\Tests\AnnotationTest;
use Biigle\Tests\AnnotationLabelTest;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Support\Detect;
use Biigle\Modules\Laserpoints\Jobs\ProcessManualChunkJob;

class ProcessManualChunkJobTest extends TestCase
{
    protected $image;
    protected $points;

    public function setUp()
    {
        parent::setUp();
        $this->image = Image::convert(ImageTest::create());
        $this->points = collect([
            $this->image->id => collect(['[100,100]', '[200,200]', '[300,300]']),
        ]);
    }

    public function testHandle()
    {
        $mock = Mockery::mock(Detect::class);
        $mock->shouldReceive('execute')
            ->once()
            ->with(
                "{$this->image->volume->url}/{$this->image->filename}",
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


        with(new ProcessManualChunkJob($this->image->volume->url, $this->points, 30))->handle();

        $expect = [
            'area' => 100,
            'px' => 50000,
            'count' => 3,
            'method' => 'manual',
            'points' => [[100, 100], [200, 200], [300, 300]],
            'error' => false,
            'distance' => 30,
        ];

        $this->assertEquals($expect, $this->image->fresh()->laserpoints);
    }

    public function testHandleGracefulError()
    {
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

        with(new ProcessManualChunkJob($this->image->volume->url, $this->points, 30))->handle();

        $expect = [
            'error' => true,
            'message' => 'Some expected error occurred.',
            'distance' => 30,
        ];

        $this->assertEquals($expect, $this->image->fresh()->laserpoints);
    }

    public function testHandleFatalError()
    {
        // previous laserpoint detection results should be removed
        $this->image->laserpoints = [
            'area' => 100,
            'px' => 50000,
            'count' => 3,
            'method' => 'manual',
            'points' => [[100, 100], [200, 200], [300, 300]],
            'error' => false,
            'distance' => 30,
        ];
        $this->image->save();

        $mock = Mockery::mock(Detect::class);
        $mock->shouldReceive('execute')
            ->once()
            ->andThrow(new Exception('Fatal error message.'));

        App::singleton(Detect::class, function () use ($mock) {
            return $mock;
        });

        with(new ProcessManualChunkJob($this->image->volume->url, $this->points, 30))->handle();

        $expect = [
            'error' => true,
            'message' => 'Fatal error message.',
            'distance' => 30,
        ];

        $this->assertEquals($expect, $this->image->fresh()->laserpoints);
    }
}
