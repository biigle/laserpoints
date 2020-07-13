<?php

namespace Biigle\Tests\Modules\Laserpoints\Http\Controllers\Api;

use ApiTestCase;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Tests\ImageTest;

class VolumeImageControllerTest extends ApiTestCase
{
    public function testIndex()
    {
        ImageTest::create(['volume_id' => $this->volume()->id, 'filename' => 'abc']);
        $image = Image::convert(ImageTest::create(['volume_id' => $this->volume()->id]));
        $id = $this->volume()->id;

        $this->doTestApiRoute('GET', "/api/v1/volumes/{$id}/images/filter/laserpoints");

        $this->beUser();
        $response = $this->get("/api/v1/volumes/{$id}/images/filter/laserpoints");
        $response->assertStatus(403);

        $this->beGuest();
        $response = $this->json('GET', "/api/v1/volumes/{$id}/images/filter/laserpoints")
            ->assertExactJson([]);
        $response->assertStatus(200);

        $image->laserpoints = ['error' => true, 'message' => 'abc'];
        $image->save();
        $image->save();
        $response = $this->json('GET', "/api/v1/volumes/{$id}/images/filter/laserpoints")
            ->assertExactJson([]);
        $response->assertStatus(200);

        $image->laserpoints = ['error' => false, 'method' => 'manual'];
        $image->save();
        $response = $this->json('GET', "/api/v1/volumes/{$id}/images/filter/laserpoints")
            ->assertExactJson([]);
        $response->assertStatus(200);

        $image->laserpoints = ['error' => false, 'method' => 'delphi'];
        $image->save();
        $response = $this->json('GET', "/api/v1/volumes/{$id}/images/filter/laserpoints")
            ->assertExactJson([$image->id]);
        $response->assertStatus(200);
    }
}
