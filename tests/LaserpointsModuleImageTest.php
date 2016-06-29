<?php

use Dias\Modules\Laserpoints\Image;
use Dias\Shape;

class LaserpointsModuleImageTest extends TestCase {

    public function testConvert()
    {
        $image = ImageTest::create([
            'attrs' => [
                Image::LASERPOINTS_ATTRIBUTE => [
                    'px' => 500
                ]
            ]
        ]);
        $laserpointsImage = Image::convert($image);
        $this->assertEquals($image->id, $laserpointsImage->id);
        $this->assertTrue($laserpointsImage instanceof Image);
        $this->assertEquals(500, $laserpointsImage->laserpoints['px']);
    }

    public function testLaserpoints()
    {
        $image = Image::convert(ImageTest::create());
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
        $image = Image::convert(ImageTest::create(['attrs' => ['something' => 'else']]));
        // no error is thrown
        $this->assertNull($image->laserpoints);
    }
}
