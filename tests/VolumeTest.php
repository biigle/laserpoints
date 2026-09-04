<?php

namespace Biigle\Tests\Modules\Laserpoints;

use Biigle\Image;
use Biigle\ImageAnnotation;
use Biigle\Modules\Laserpoints\Volume;
use Biigle\Tests\LabelTest;
use Biigle\Tests\VolumeTest as BaseVolumeTest;
use Exception;
use TestCase;

class VolumeTest extends TestCase
{
    public function testConvert()
    {
        $volume = BaseVolumeTest::create();
        $laserpointsVolume = Volume::convert($volume);
        $this->assertSame($volume->id, $laserpointsVolume->id);
        $this->assertTrue($laserpointsVolume instanceof Volume);
    }

    public function testReadyForManualDetection()
    {
        $volume = Volume::convert(BaseVolumeTest::create());
        $label = LabelTest::create();

        $images = Image::class::factory()
            ->count(4)
            ->sequence(function ($i) use ($volume) {
                return [
                    'filename' => uniqid(),
                    'volume_id' => $volume->id,
                ];
            })
            ->create();

        $images->each(function ($i) use ($label) {
            ImageTest::addLaserpoints($i, $label);
        });
        try {
            $volume->readyForManualDetection($label);
            $this->assertFalse(true);
        } catch (Exception $e) {
            $this->assertStringContainsString('at least 2 manually annotated laser points per image', $e->getMessage());
        }

        $images->each(function ($i) use ($label) {
            ImageTest::addLaserpoints($i, $label, 3);
        });
        // Succeeds.
        $volume->readyForManualDetection($label);

        $images->each(function ($i) use ($label) {
            ImageTest::addLaserpoints($i, $label);
        });
        try {
            $volume->readyForManualDetection($label);
            $this->assertFalse(true);
        } catch (Exception $e) {
            $this->assertStringContainsString('can\'t be more than 4 manually annotated laser points per image', $e->getMessage());
        }

        ImageAnnotation::getQuery()->delete();
        $images->each(function ($i) use ($label) {
            ImageTest::addLaserpoints($i, $label, 3);
        });
        ImageTest::addLaserpoints($images[0], $label, 1);

        try {
            $volume->readyForManualDetection($label);
            $this->assertFalse(true);
        } catch (Exception $e) {
            $this->assertStringContainsString('must have equal count of manually annotated laser points', $e->getMessage());
        }
    }

    public function testReadyForManualDetectionOutOfBounds()
    {
        $volume = Volume::convert(BaseVolumeTest::create());
        $label = LabelTest::create();

        $images = Image::class::factory()
            ->count(4)
            ->sequence(function ($i) use ($volume) {
                return [
                    'filename' => uniqid(),
                    'volume_id' => $volume->id,
                    'attrs' => ['width' => 100, 'height' => 100],
                ];
            })
            ->create();

        $images->each(function ($i) use ($label) {
            ImageTest::addLaserpoints($i, $label, 2);
        });

        // Delete one point and make the other invalid. This image will be ignored because
        // of the invalid point so no error (invalid count) will be thrown below.
        $annotation = $images->first()->annotations()->first()->delete();
        $annotation = $images->first()->annotations()->first();
        // The point is outside the image boundaries.
        $annotation->points = [50, 101];
        $annotation->save();

        // No exception is thrown.
        $volume->readyForManualDetection($label);
        $this->expectNotToPerformAssertions();
    }

    public function testHasDetectedLaserpoints()
    {
        $volume = Volume::convert(BaseVolumeTest::create());

        $images = Image::class::factory()
            ->count(4)
            ->sequence(function ($i) use ($volume) {
                return [
                    'filename' => uniqid(),
                    'volume_id' => $volume->id,
                ];
            })
            ->create();

        $this->assertFalse($volume->hasDetectedLaserpoints());

        $image = $images[0];
        $image->attrs = ['laserpoints' => [
            'error' => true,
        ]];
        $image->save();
        $this->assertFalse($volume->hasDetectedLaserpoints());

        $image->attrs = ['laserpoints' => [
            'error' => false,
            'method' => 'manual',
        ]];
        $image->save();
        $this->assertFalse($volume->hasDetectedLaserpoints());

        $image->attrs = ['laserpoints' => [
            'error' => false,
            'method' => 'delphi',
        ]];
        $image->save();
        $this->assertTrue($volume->hasDetectedLaserpoints());
    }
}
