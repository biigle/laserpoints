<?php

namespace Biigle\Tests\Modules\Laserpoints;

use Biigle\Image;
use Biigle\ImageAnnotation;
use Biigle\Modules\Laserpoints\Volume;
use Biigle\Tests\ImageAnnotationLabelTest;
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

        try {
            $volume->readyForManualDetection($label);
            $this->assertFalse(true);
        } catch (Exception $e) {
            $this->assertStringContainsString('No image has manually annotated laser points', $e->getMessage());
        }

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

    public function testReadyForManualDetectionDuplicateLabels()
    {
        $volume = Volume::convert(BaseVolumeTest::create());
        $label = LabelTest::create();

        $images = Image::class::factory()
            ->count(2)
            ->sequence(function ($i) use ($volume) {
                return [
                    'filename' => uniqid(),
                    'volume_id' => $volume->id,
                ];
            })
            ->create();

        $images->each(function ($i) use ($label) {
            ImageTest::addLaserpoints($i, $label, 2);
        });

        // The same label can be attached to the same annotation by multiple users. This
        // must not be counted as an additional laser point, else the images would appear
        // to have a different count of laser points.
        ImageAnnotationLabelTest::create([
            'annotation_id' => $images->first()->annotations()->first()->id,
            'label_id' => $label->id,
        ]);

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

    public function testHasLaserpoints()
    {
        $volume = Volume::convert(BaseVolumeTest::create());
        $image = Image::factory()->create(['volume_id' => $volume->id]);
        $this->assertFalse($volume->hasLaserpoints());

        $image->attrs = ['size' => 123];
        $image->save();
        $this->assertFalse($volume->hasLaserpoints());

        $image->attrs = ['laserpoints' => ['error' => true]];
        $image->save();
        $this->assertTrue($volume->hasLaserpoints());

        $other = Volume::convert(BaseVolumeTest::create());
        $this->assertFalse($other->hasLaserpoints());
    }

    public function testDeleteLaserpoints()
    {
        $volume = Volume::convert(BaseVolumeTest::create());
        $image1 = Image::factory()->create([
            'volume_id' => $volume->id,
            'filename' => 'a.jpg',
            'attrs' => [
                'laserpoints' => ['area' => 2, 'method' => 'lp-automatic'],
                'size' => 123,
            ],
        ]);
        $image2 = Image::factory()->create([
            'volume_id' => $volume->id,
            'filename' => 'b.jpg',
            'attrs' => ['laserpoints' => ['error' => true, 'method' => 'manual']],
        ]);
        $image3 = Image::factory()->create([
            'volume_id' => $volume->id,
            'filename' => 'c.jpg',
            'attrs' => ['size' => 456],
        ]);
        $other = Image::factory()->create([
            'attrs' => ['laserpoints' => ['area' => 3]],
        ]);

        $this->assertSame(2, $volume->deleteLaserpoints());

        $this->assertSame(['size' => 123], $image1->fresh()->attrs);
        $this->assertNull($image2->fresh()->attrs);
        $this->assertSame(['size' => 456], $image3->fresh()->attrs);
        $this->assertSame(['laserpoints' => ['area' => 3]], $other->fresh()->attrs);
        $this->assertFalse($volume->hasLaserpoints());
    }
}
