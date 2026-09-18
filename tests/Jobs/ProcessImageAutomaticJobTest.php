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
use Biigle\Modules\Laserpoints\Support\DetectionLock;
use Biigle\Tests\ImageTest;
use Cache;
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
            ->with(Mockery::any(), 30, null, 2)
            ->andReturn([
                'error' => false,
                'area' => 100,
                'count' => 3,
                'method' => 'manual',
                'points' => [[100, 100], [100, 100], [100, 100]],
                'channel_mode' => 'red',
            ]);

        App::singleton(DetectAutomatic::class, function () use ($mock) {
            return $mock;
        });

        with(new ProcessImageAutomaticJob($this->image, 30))->handle();

        $actual = $this->image->fresh()->laserpoints;
        $this->assertSame(false, $actual['error']);
        $this->assertSame(100, $actual['area']);
        $this->assertSame(3, $actual['count']);
        $this->assertSame('manual', $actual['method']);
        $this->assertSame([[100, 100], [100, 100], [100, 100]], $actual['points']);
        $this->assertSame(30, $actual['distance']);
        $this->assertSame('red', $actual['channel_mode']);
        // Previously set attributes should not be lost.
        $this->assertSame(1, $this->image->fresh()->attrs['a']);
    }

    public function testHandleChannelMode()
    {
        $mock = Mockery::mock(DetectAutomatic::class);
        $mock->shouldReceive('execute')
            ->once()
            ->with(Mockery::any(), 30, 'red', 2)
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

        with(new ProcessImageAutomaticJob($this->image, 30, 'red'))->handle();

        $actual = $this->image->fresh()->laserpoints;
        $this->assertSame(false, $actual['error']);
        $this->assertSame(100, $actual['area']);
        $this->assertSame(3, $actual['count']);
        $this->assertSame('manual', $actual['method']);
        $this->assertSame([[100, 100], [100, 100], [100, 100]], $actual['points']);
        $this->assertSame(30, $actual['distance']);
        $this->assertSame('red', $actual['channel_mode']);
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

        $actual = $this->image->fresh()->laserpoints;
        $this->assertSame(true, $actual['error']);
        $this->assertSame('Some expected error occurred.', $actual['message']);
        $this->assertSame(30, $actual['distance']);
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

        $actual = $this->image->fresh()->laserpoints;
        $this->assertSame(true, $actual['error']);
        $this->assertSame('Fatal error message.', $actual['message']);
        $this->assertSame(30, $actual['distance']);
    }

    public function testHandleReleasesImageLock()
    {
        $this->mockDetection();
        DetectionLock::acquireImage($this->image->volume_id, $this->image->id);

        with(new ProcessImageAutomaticJob($this->image, 30))->handle();
        $this->assertFalse(Cache::has(DetectionLock::key($this->image->volume_id)));
    }

    public function testFailedReleasesImageLock()
    {
        DetectionLock::acquireImage($this->image->volume_id, $this->image->id);

        with(new ProcessImageAutomaticJob($this->image, 30))->failed(new Exception);
        $this->assertFalse(Cache::has(DetectionLock::key($this->image->volume_id)));
    }

    public function testHandleBatchKeepsVolumeLock()
    {
        $this->mockDetection();
        DetectionLock::acquireVolume($this->image->volume_id);

        with(new ProcessImageAutomaticJob($this->image, 30, batch: true))->handle();
        $this->assertTrue(Cache::has(DetectionLock::key($this->image->volume_id)));
    }

    public function testHandleImageDeleted()
    {
        $mock = Mockery::mock(DetectAutomatic::class);
        $mock->shouldReceive('execute')->never();
        App::singleton(DetectAutomatic::class, fn () => $mock);
        DetectionLock::acquireImage($this->image->volume_id, $this->image->id);
        $job = new ProcessImageAutomaticJob($this->image, 30);
        $this->image->delete();

        $job->handle();
        $this->assertFalse(Cache::has(DetectionLock::key($this->image->volume_id)));
    }

    public function testHandleImageDeletedBatch()
    {
        $mock = Mockery::mock(DetectAutomatic::class);
        $mock->shouldReceive('execute')->never();
        App::singleton(DetectAutomatic::class, fn () => $mock);
        DetectionLock::acquireVolume($this->image->volume_id);
        $job = new ProcessImageAutomaticJob($this->image, 30, batch: true);
        $this->image->delete();

        $job->handle();
        $this->assertTrue(Cache::has(DetectionLock::key($this->image->volume_id)));
    }

    protected function mockDetection()
    {
        $mock = Mockery::mock(DetectAutomatic::class);
        $mock->shouldReceive('execute')->andReturn(['error' => false]);
        App::singleton(DetectAutomatic::class, fn () => $mock);
    }
}
