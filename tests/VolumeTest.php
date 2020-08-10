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
        $this->assertEquals($volume->id, $laserpointsVolume->id);
        $this->assertTrue($laserpointsVolume instanceof Volume);
    }

    public function testReadyForDelphiDetection()
    {
        $volume = Volume::convert(BaseVolumeTest::create());
        $label = LabelTest::create();

        $images = factory(Image::class, 4)->create()
            ->each(function ($i) use ($volume) {
                $i->filename = uniqid();
                $i->volume_id = $volume->id;
                $i->save();
            });

        try {
            $volume->readyForDelphiDetection($label);
            $this->assertFalse(true);
        } catch (Exception $e) {
            $this->assertStringContainsString('At least 4 are required', $e->getMessage());
        }

        $images->each(function ($i) use ($label) {
            ImageTest::addLaserpoints($i, $label);
        });
        try {
            $volume->readyForDelphiDetection($label);
            $this->assertFalse(true);
        } catch (Exception $e) {
            $this->assertStringContainsString('at least 2 manually annotated laser points per image', $e->getMessage());
        }

        $images->each(function ($i) use ($label) {
            ImageTest::addLaserpoints($i, $label, 3);
        });
        // Succeeds.
        $volume->readyForDelphiDetection($label);

        $images->each(function ($i) use ($label) {
            ImageTest::addLaserpoints($i, $label);
        });
        try {
            $volume->readyForDelphiDetection($label);
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
            $volume->readyForDelphiDetection($label);
            $this->assertFalse(true);
        } catch (Exception $e) {
            $this->assertStringContainsString('must have equal count of manually annotated laser points', $e->getMessage());
        }
    }

    public function testReadyForDelphiDetectionOutOfBounds()
    {
        $volume = Volume::convert(BaseVolumeTest::create());
        $label = LabelTest::create();

        $images = factory(Image::class, 4)->create()
            ->each(function ($i) use ($volume) {
                $i->filename = uniqid();
                $i->volume_id = $volume->id;
                $i->width = 100;
                $i->height = 100;
                $i->save();
            });

        $images->each(function ($i) use ($label) {
            ImageTest::addLaserpoints($i, $label, 2);
        });

        $annotation = $images->first()->annotations()->first();

        // The point is outside the image boundaries.
        $annotation->points = [50, 101];
        $annotation->save();

        try {
            // This fails because images with invalid points are not considered.
            $volume->readyForDelphiDetection($label);
            $this->assertFalse(true);
        } catch (Exception $e) {
            $this->assertStringContainsString('At least 4 are required', $e->getMessage());
        }
    }

    public function testHasDetectedLaserpoints()
    {
        $volume = Volume::convert(BaseVolumeTest::create());
        $images = factory(Image::class, 4)->create()
            ->each(function ($i) use ($volume) {
                $i->filename = uniqid();
                $i->volume_id = $volume->id;
                $i->save();
            });

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
