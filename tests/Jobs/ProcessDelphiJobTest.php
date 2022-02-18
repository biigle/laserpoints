<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use App;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Jobs\ProcessDelphiJob;
use Biigle\Modules\Laserpoints\Support\DelphiApply;
use Biigle\Tests\ImageTest;
use Cache;
use Exception;
use File;
use FileCache;
use Mockery;
use Storage;
use TestCase;

class ProcessDelphiJobTest extends TestCase
{
    protected $image;
    protected $gatherFile;

    public function setUp(): void
    {
        parent::setUp();
        $this->image = Image::convert(ImageTest::create(['attrs' => ['a' => 1]]));
        $this->gatherFile = 'my-gather-file';
        config(['laserpoints.tmp_dir' => '/tmp']);
        FileCache::fake();
        Storage::fake('laserpoints');
    }

    public function testHandle()
    {
        $disk = Storage::disk('laserpoints');
        $disk->put($this->gatherFile, 'test');
        $mock = Mockery::mock(DelphiApply::class);
        $mock->shouldReceive('execute')
            ->once()
            ->with("/tmp/{$this->gatherFile}", Mockery::any(), 30)
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

        with(new ProcessDelphiJob($this->image, 30, $this->gatherFile))->handle();

        $expect = [
            'error' => true,
            'message' => 'Fatal error message.',
            'distance' => 30,
        ];

        $this->assertEquals($expect, $this->image->fresh()->laserpoints);
    }
}
