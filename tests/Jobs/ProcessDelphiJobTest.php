<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use App;
use File;
use Cache;
use Mockery;
use TestCase;
use Exception;
use FileCache;
use Biigle\Tests\ImageTest;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Support\DelphiApply;
use Biigle\Modules\Laserpoints\Jobs\ProcessDelphiJob;

class ProcessDelphiJobTest extends TestCase
{
    protected $image;
    protected $gatherFile;

    public function setUp(): void
    {
        parent::setUp();
        $this->image = Image::convert(ImageTest::create(['attrs' => ['a' => 1]]));
        $this->gatherFile = '/my/gather/file';
        FileCache::fake();
    }

    public function testHandle()
    {
        $mock = Mockery::mock(DelphiApply::class);
        $mock->shouldReceive('execute')
            ->once()
            ->with($this->gatherFile, Mockery::any(), 30)
            ->andReturn([
                'error' => false,
                'area' => 100,
                'count' => 3,
                'method' => 'delphi',
                'points' => [[100, 100], [200, 200], [300, 300]],
            ]);

        App::singleton(DelphiApply::class, function () use ($mock) {
            return $mock;
        });

        File::shouldReceive('delete')->once()->with($this->gatherFile);
        with(new ProcessDelphiJob($this->image, 30, $this->gatherFile))->handle();

        $expect = [
            'area' => 100,
            'count' => 3,
            'method' => 'delphi',
            'points' => [[100, 100], [200, 200], [300, 300]],
            'error' => false,
            'distance' => 30,
        ];

        $this->assertEquals($expect, $this->image->fresh()->laserpoints);
        // Previously set attrs should not be lost.
        $this->assertEquals(1, $this->image->fresh()->attrs['a']);
    }

    public function testHandleCountDecrease()
    {
        Cache::forever('test_job_count', 2);

        $mock = Mockery::mock(DelphiApply::class);
        $mock->shouldReceive('execute')
            ->once()
            ->andReturn([]);

        App::singleton(DelphiApply::class, function () use ($mock) {
            return $mock;
        });

        File::shouldReceive('delete')->never();
        with(new ProcessDelphiJob($this->image, 30, $this->gatherFile, 'test_job_count'))->handle();

        $this->assertEquals(1, Cache::get('test_job_count'));
    }

    public function testHandleCountZero()
    {
        Cache::forever('test_job_count', 1);

        $mock = Mockery::mock(DelphiApply::class);
        $mock->shouldReceive('execute')
            ->once()
            ->andReturn([]);

        App::singleton(DelphiApply::class, function () use ($mock) {
            return $mock;
        });

        File::shouldReceive('delete')->once()->with($this->gatherFile);
        with(new ProcessDelphiJob($this->image, 30, $this->gatherFile, 'test_job_count'))->handle();
        $this->assertFalse(Cache::has('test_job_count'));
    }

    public function testHandleGracefulError()
    {
        $mock = Mockery::mock(DelphiApply::class);
        $mock->shouldReceive('execute')
            ->once()
            ->andReturn([
                'error' => true,
                'message' => 'Some expected error occurred.',
            ]);

        App::singleton(DelphiApply::class, function () use ($mock) {
            return $mock;
        });

        File::shouldReceive('delete')->once()->with($this->gatherFile);
        with(new ProcessDelphiJob($this->image, 30, $this->gatherFile))->handle();

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
            'count' => 3,
            'method' => 'delphi',
            'images' => [[100, 100], [200, 200], [300, 300]],
            'error' => false,
            'distance' => 30,
        ];
        $this->image->save();

        $mock = Mockery::mock(DelphiApply::class);
        $mock->shouldReceive('execute')
            ->once()
            ->andThrow(new Exception('Fatal error message.'));

        App::singleton(DelphiApply::class, function () use ($mock) {
            return $mock;
        });

        File::shouldReceive('delete')->once()->with($this->gatherFile);
        with(new ProcessDelphiJob($this->image, 30, $this->gatherFile))->handle();

        $expect = [
            'error' => true,
            'message' => 'Fatal error message.',
            'distance' => 30,
        ];

        $this->assertEquals($expect, $this->image->fresh()->laserpoints);
    }

    public function testFailed()
    {
        File::shouldReceive('delete')->once()->with($this->gatherFile);
        with(new ProcessDelphiJob($this->image, 30, $this->gatherFile))->failed();
    }
}
