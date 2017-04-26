<?php

namespace Biigle\Tests\Modules\Laserpoints\Http\Controllers\Api;

use ApiTestCase;
use Biigle\Tests\ImageTest;
use Biigle\Modules\Laserpoints\Image;

class VolumeImageControllerTest extends ApiTestCase
{
    public function testIndex()
    {
        ImageTest::create(['volume_id' => $this->volume()->id, 'filename' => 'abc']);
        $image = Image::convert(ImageTest::create(['volume_id' => $this->volume()->id]));
        $id = $this->volume()->id;

        $this->doTestApiRoute('GET', "/api/v1/volumes/{$id}/images/filter/laserpoints");

        $this->beUser();
        $this->get("/api/v1/volumes/{$id}/images/filter/laserpoints");
        $this->assertResponseStatus(403);

        $this->beGuest();
        $this->json('GET', "/api/v1/volumes/{$id}/images/filter/laserpoints")
            ->seeJsonEquals([]);
        $this->assertResponseOk();

        $image->laserpoints = ['error' => true, 'message' => 'abc'];
        $image->save();
        $image->save();
        $this->json('GET', "/api/v1/volumes/{$id}/images/filter/laserpoints")
            ->seeJsonEquals([]);
        $this->assertResponseOk();

        $image->laserpoints = ['error' => false, 'method' => 'manual'];
        $image->save();
        $this->json('GET', "/api/v1/volumes/{$id}/images/filter/laserpoints")
            ->seeJsonEquals([]);
        $this->assertResponseOk();

        $image->laserpoints = ['error' => false, 'method' => 'delphi'];
        $image->save();
        $this->json('GET', "/api/v1/volumes/{$id}/images/filter/laserpoints")
            ->seeJsonEquals([$image->id]);
        $this->assertResponseOk();
    }
}
