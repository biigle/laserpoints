<?php

namespace Biigle\Tests\Modules\Laserpoints;

use TestCase;
use Exception;
use Biigle\Label;
use Biigle\Shape;
use Biigle\Tests\LabelTest;
use Biigle\Tests\AnnotationTest;
use Biigle\Tests\AnnotationLabelTest;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Tests\ImageTest as BaseImageTest;

class ImageTest extends TestCase
{
    public static function addLaserpoints(\Biigle\Image $image, $count = 1)
    {
        $labelId = config('laserpoints.label_id');
        if (!Label::where('id', $labelId)->exists()) {
            LabelTest::create(['id' => $labelId, 'name' => 'Laserpoint']);
        }

        for ($i = 0; $i < $count; $i++) {
            $id = AnnotationTest::create([
                'image_id' => $image->id,
                'shape_id' => Shape::$pointId,
            ])->id;
            AnnotationLabelTest::create([
                'annotation_id' => $id,
                'label_id' => $labelId,
            ]);
        }
    }

    public function testConvert()
    {
        $image = BaseImageTest::create([
            'attrs' => [
                Image::LASERPOINTS_ATTRIBUTE => [
                    'px' => 500,
                ],
            ],
        ]);
        $laserpointsImage = Image::convert($image);
        $this->assertEquals($image->id, $laserpointsImage->id);
        $this->assertTrue($laserpointsImage instanceof Image);
        $this->assertEquals(500, $laserpointsImage->laserpoints['px']);
    }

    public function testLaserpoints()
    {
        $image = Image::convert(BaseImageTest::create());
        $image->laserpoints = [
            'px' => 500,
        ];
        $image->save();

        $expect = [
            'px' => 500,
        ];
        $this->assertEquals($expect, $image->fresh()->laserpoints);
    }

    public function testLaserpointsNotThere()
    {
        $image = Image::convert(BaseImageTest::create(['attrs' => ['something' => 'else']]));
        // no error is thrown
        $this->assertNull($image->laserpoints);
    }

    public function testReadyForManualDetection()
    {
        $image = Image::convert(BaseImageTest::create());

        $this->assertFalse($image->readyForManualDetection());
        self::addLaserpoints($image);

        try {
            $image->readyForManualDetection();
            $this->assertFalse(true);
        } catch (Exception $e) {
            $this->assertContains('must have at least 2 manually annotated laser points', $e->getMessage());
        }

        self::addLaserpoints($image);
        $this->assertTrue($image->readyForManualDetection());
        self::addLaserpoints($image);
        self::addLaserpoints($image);
        $this->assertTrue($image->readyForManualDetection());
        self::addLaserpoints($image);

        try {
            $image->readyForManualDetection();
            $this->assertFalse(true);
        } catch (Exception $e) {
            $this->assertContains('can\'t have more than 4 manually annotated laser points', $e->getMessage());
        }
    }
}
