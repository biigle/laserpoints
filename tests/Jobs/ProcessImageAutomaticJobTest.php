<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use App;
use Biigle\Label;
use Biigle\Shape;
use Biigle\ImageAnnotation;
use Biigle\ImageAnnotationLabel;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageAutomaticJob;
use Biigle\Modules\Laserpoints\Support\DetectAutomatic;
use Biigle\Tests\ImageTest;
use Exception;
use Mockery;
use TestCase;

class ProcessImageAutomaticJobTest extends TestCase
{
    protected $image;

    public function setUp(): void
    {
        parent::setUp();
        $this->image = Image::convert(ImageTest::create(['attrs' => ['a' => 1]]));
    }

    public function testHandle()
    {
        $mock = Mockery::mock(DetectAutomatic::class);
        $mock->shouldReceive('execute')
            ->once()
            ->with(Mockery::any(), 30, null)
            ->andReturn([
                'error' => false,
                'area' => 100,
                'count' => 3,
                'method' => 'manual',
                'points' => [[100, 100], [100, 100], [100, 100]],
            ]);

        App::singleton(DetectAutomatic::class, function () use ($mock) {
            return $mock;
        });

        with(new ProcessImageAutomaticJob($this->image, 30))->handle();

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

    public function testHandleLineInfo()
    {
        $mock = Mockery::mock(DetectAutomatic::class);
        $mock->shouldReceive('execute')
            ->once()
            ->with(Mockery::any(), 30, 'lineinfo')
            ->andReturn([
                'error' => false,
                'area' => 100,
                'count' => 3,
                'method' => 'manual',
                'points' => [[100, 100], [100, 100], [100, 100]],
            ]);

        App::singleton(DetectAutomatic::class, function () use ($mock) {
            return $mock;
        });

        with(new ProcessImageAutomaticJob($this->image, 30, 'lineinfo'))->handle();

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
        $mock = Mockery::mock(DetectAutomatic::class);
        $mock->shouldReceive('execute')
            ->once()
            ->andReturn([
                'error' => true,
                'message' => 'Some expected error occurred.',
            ]);

        App::singleton(DetectAutomatic::class, function () use ($mock) {
            return $mock;
        });

        with(new ProcessImageAutomaticJob($this->image, 30))->handle();

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

        $mock = Mockery::mock(DetectAutomatic::class);
        $mock->shouldReceive('execute')
            ->once()
            ->andThrow(new Exception('Fatal error message.'));

        App::singleton(DetectAutomatic::class, function () use ($mock) {
            return $mock;
        });

        with(new ProcessImageAutomaticJob($this->image, 30))->handle();

        $expect = [
            'error' => true,
            'message' => 'Fatal error message.',
            'distance' => 30,
        ];

        $this->assertSame($expect, $this->image->fresh()->laserpoints);
    }
}
