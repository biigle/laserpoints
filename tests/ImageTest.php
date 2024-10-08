<?php

namespace Biigle\Tests\Modules\Laserpoints;

use Biigle\Image as BaseImage;
use Biigle\Label;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Shape;
use Biigle\Tests\ImageAnnotationLabelTest;
use Biigle\Tests\ImageAnnotationTest;
use Biigle\Tests\ImageTest as BaseImageTest;
use Biigle\Tests\LabelTest;
use Exception;
use TestCase;

class ImageTest extends TestCase
{
    public static function addLaserpoints($image, $label, $count = 1)
    {
        for ($i = 0; $i < $count; $i++) {
            $id = ImageAnnotationTest::create([
                'image_id' => $image->id,
                'shape_id' => Shape::pointId(),
            ])->id;
            ImageAnnotationLabelTest::create([
                'annotation_id' => $id,
                'label_id' => $label->id,
            ]);
        }
    }

    public function testConvert()
    {
        $image = BaseImageTest::create([
            'attrs' => [Image::LASERPOINTS_ATTRIBUTE => ['area' => 500]],
        ]);
        $laserpointsImage = Image::convert($image);
        $this->assertSame($image->id, $laserpointsImage->id);
        $this->assertTrue($laserpointsImage instanceof Image);
        $this->assertSame(500, $laserpointsImage->laserpoints['area']);
    }

    public function testLaserpoints()
    {
        $image = Image::convert(BaseImageTest::create());
        $image->laserpoints = [
            'area' => 500,
        ];
        $image->save();

        $expect = [
            'area' => 500,
        ];
        $this->assertSame($expect, $image->fresh()->laserpoints);
    }

    public function testAreaAttribute()
    {
        $image = Image::convert(BaseImageTest::create());
        $this->assertNull($image->area);

        $image->metadata = [
            'area' => 600,
        ];
        $image->save();
        $this->assertSame(600, $image->fresh()->area);

        // Laser point detection overrides the metadata.
        $image->laserpoints = [
            'area' => 500,
        ];
        $image->save();

        $this->assertSame(500, $image->fresh()->area);
    }

    public function testLaserpointsNotThere()
    {
        $image = Image::convert(BaseImageTest::create(['attrs' => ['something' => 'else']]));
        // no error is thrown
        $this->assertNull($image->laserpoints);
    }

    public function testReadyForManualDetection()
    {
        $label = LabelTest::create();
        $image = Image::convert(BaseImageTest::create());

        $this->assertFalse($image->readyForManualDetection($label));
        static::addLaserpoints($image, $label);

        try {
            $image->readyForManualDetection($label);
            $this->assertFalse(true);
        } catch (Exception $e) {
            $this->assertStringContainsString('must have at least 2 manually annotated laser points', $e->getMessage());
        }

        static::addLaserpoints($image, $label);
        $this->assertTrue($image->readyForManualDetection($label));
        static::addLaserpoints($image, $label, 2);
        $this->assertTrue($image->readyForManualDetection($label));
        static::addLaserpoints($image, $label);

        try {
            $image->readyForManualDetection($label);
            $this->assertFalse(true);
        } catch (Exception $e) {
            $this->assertStringContainsString('can\'t have more than 4 manually annotated laser points', $e->getMessage());
        }
    }
}
