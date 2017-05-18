<?php

namespace Biigle\Tests\Modules\Laserpoints;

use TestCase;
use Exception;
use Biigle\Image;
use Biigle\Annotation;
use Biigle\Modules\Laserpoints\Volume;
use Biigle\Tests\VolumeTest as BaseVolumeTest;

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

        $images = factory(Image::class, 4)->create()
            ->each(function ($i) use ($volume) {
                $i->filename = uniqid();
                $i->volume_id = $volume->id;
                $i->save();
            });

        try {
            $volume->readyForDelphiDetection();
            $this->assertFalse(true);
        } catch (Exception $e) {
            $this->assertContains('At least 4 are required', $e->getMessage());
        }

        $images->each(function ($i) {
            ImageTest::addLaserpoints($i);
        });
        try {
            $volume->readyForDelphiDetection();
            $this->assertFalse(true);
        } catch (Exception $e) {
            $this->assertContains('at least 2 manually annotated laser points per image', $e->getMessage());
        }

        $images->each(function ($i) {
            ImageTest::addLaserpoints($i, 3);
        });
        // Succeeds.
        $volume->readyForDelphiDetection();

        $images->each(function ($i) {
            ImageTest::addLaserpoints($i);
        });
        try {
            $volume->readyForDelphiDetection();
            $this->assertFalse(true);
        } catch (Exception $e) {
            $this->assertContains('can\'t be more than 4 manually annotated laser points per image', $e->getMessage());
        }

        Annotation::first()->delete();
        try {
            $volume->readyForDelphiDetection();
            $this->assertFalse(true);
        } catch (Exception $e) {
            $this->assertContains('must have equal count of manually annotated laser points', $e->getMessage());
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
