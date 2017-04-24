<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use App;
use File;
use Mockery;
use TestCase;
use Exception;
use Biigle\Shape;
use Biigle\Tests\ImageTest;
use Biigle\Tests\LabelTest;
use Biigle\Tests\AnnotationTest;
use Biigle\Tests\AnnotationLabelTest;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Support\DelphiApply;
use Biigle\Modules\Laserpoints\Jobs\ProcessDelphiChunkJob;

class ProcessDelphiChunkJobTest extends TestCase
{
    protected $image;
    protected $images;
    protected $gatherFile;

    public function setUp()
    {
        parent::setUp();
        $this->image = Image::convert(ImageTest::create());
        $this->images = collect($this->image->id);
        $this->gatherFile = '/my/gather/file';
    }

    public function testHandle()
    {
        $mock = Mockery::mock(DelphiApply::class);
        $mock->shouldReceive('execute')
            ->once()
            ->with(
                $this->gatherFile,
                "{$this->image->volume->url}/{$this->image->filename}",
                30
            )
            ->andReturn([
                'error' => false,
                'area' => 100,
                'px' => 50000,
                'count' => 3,
                'method' => 'delphi',
                'points' => [[100, 100], [200, 200], [300, 300]],
            ]);

        App::singleton(DelphiApply::class, function () use ($mock) {
            return $mock;
        });

        File::shouldReceive('delete')->once()->with($this->gatherFile);
        with(new ProcessDelphiChunkJob($this->image->volume->url, $this->images, 30, $this->gatherFile))->handle();

        $expect = [
            'area' => 100,
            'px' => 50000,
            'count' => 3,
            'method' => 'delphi',
            'points' => [[100, 100], [200, 200], [300, 300]],
            'error' => false,
            'distance' => 30,
        ];

        $this->assertEquals($expect, $this->image->fresh()->laserpoints);
    }

    public function testHandleCountDecrease()
    {
        $countFile = sys_get_temp_dir().'/biigle_delphi_chunk_job_test';
        File::put($countFile, 10);

        try {
            $mock = Mockery::mock(DelphiApply::class);
            $mock->shouldReceive('execute')
                ->once()
                ->andReturn([]);

            App::singleton(DelphiApply::class, function () use ($mock) {
                return $mock;
            });

            File::shouldReceive('delete')->never();
            with(new ProcessDelphiChunkJob($this->image->volume->url, $this->images, 30, $this->gatherFile, $countFile))->handle();

            $this->assertEquals('9', file_get_contents($countFile));
        } finally {
            unlink($countFile);
        }
    }

    public function testHandleCountZero()
    {
        $countFile = sys_get_temp_dir().'/biigle_delphi_chunk_job_test';
        File::put($countFile, 1);

        try {
            $mock = Mockery::mock(DelphiApply::class);
            $mock->shouldReceive('execute')
                ->once()
                ->andReturn([]);

            App::singleton(DelphiApply::class, function () use ($mock) {
                return $mock;
            });

            File::shouldReceive('delete')->once()->with($countFile);
            File::shouldReceive('delete')->once()->with($this->gatherFile);
            with(new ProcessDelphiChunkJob($this->image->volume->url, $this->images, 30, $this->gatherFile, $countFile))->handle();
        } finally {
            unlink($countFile);
        }
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
        with(new ProcessDelphiChunkJob($this->image->volume->url, $this->images, 30, $this->gatherFile))->handle();

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
        with(new ProcessDelphiChunkJob($this->image->volume->url, $this->images, 30, $this->gatherFile))->handle();

        $expect = [
            'error' => true,
            'message' => 'Fatal error message.',
            'distance' => 30,
        ];

        $this->assertEquals($expect, $this->image->fresh()->laserpoints);
    }
}
