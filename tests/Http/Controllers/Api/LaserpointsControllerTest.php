<?php

namespace Biigle\Tests\Modules\Laserpoints\Http\Controllers\Api;

use ApiTestCase;
use Biigle\Label;
use Biigle\Shape;
use Biigle\Image;
use Biigle\Tests\LabelTest;
use Biigle\Tests\ImageTest;
use Biigle\Tests\AnnotationTest;
use Biigle\Tests\AnnotationLabelTest;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageManualJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageDelphiJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeDelphiJob;

class LaserpointsControllerTest extends ApiTestCase
{

    public function testComputeImage()
    {
        $image = ImageTest::create(['volume_id' => $this->volume()->id]);
        $this->doTestApiRoute('POST', "/api/v1/images/{$image->id}/laserpoints/area");

        $this->beGuest();
        $this->post("/api/v1/images/{$image->id}/laserpoints/area");
        $this->assertResponseStatus(403);

        $this->beEditor();
        $this->json('POST', "/api/v1/images/{$image->id}/laserpoints/area");
        // Distance is required.
        $this->assertResponseStatus(422);

        $this->json('POST', "/api/v1/images/{$image->id}/laserpoints/area", ['distance' => 50]);
        // Not enough manually annotated images for Delphi.
        $this->assertResponseStatus(422);

        $this->makeManualAnnotations(3);

        $this->expectsJobs(ProcessImageDelphiJob::class);
        $this->json('POST', "/api/v1/images/{$image->id}/laserpoints/area", ['distance' => 50]);
        $this->assertResponseOk();

        Image::truncate();
        $this->makeManualAnnotations(1, 1);
        $image = Image::first();

        $this->json('POST', "/api/v1/images/{$image->id}/laserpoints/area", ['distance' => 50]);
        // Not enough manual annotations on this image.
        $this->assertResponseStatus(422);

        Image::truncate();
        $this->makeManualAnnotations(5, 1);
        $image = Image::first();

        $this->json('POST', "/api/v1/images/{$image->id}/laserpoints/area", ['distance' => 50]);
        // Too many manual annotations on this image.
        $this->assertResponseStatus(422);

        Image::truncate();
        $this->makeManualAnnotations(2, 1);
        $image = Image::first();

        $this->expectsJobs(ProcessImageManualJob::class);
        $this->post("/api/v1/images/{$image->id}/laserpoints/area", ['distance' => 50]);
        $this->assertResponseOk();
    }

    public function testComputeImageRemote()
    {
        $this->volume()->url = 'http://localhost';
        $this->volume()->save();
        $image = ImageTest::create(['volume_id' => $this->volume()->id]);
        $this->makeManualAnnotations(3);

        $this->beEditor();
        $this->doesntExpectJobs(ProcessImageDelphiJob::class);
        $this->json('POST', "/api/v1/images/{$image->id}/laserpoints/area", ['distance' => 50]);
        $this->assertResponseStatus(422);
    }

    public function testComputeVolume()
    {
        $id = $this->volume()->id;
        $this->doTestApiRoute('POST', "/api/v1/volumes/{$id}/laserpoints/area");

        $this->beGuest();
        $this->post("/api/v1/volumes/{$id}/laserpoints/area");
        $this->assertResponseStatus(403);

        $this->beEditor();
        $this->json('POST', "/api/v1/volumes/{$id}/laserpoints/area");
        // Missing distance
        $this->assertResponseStatus(422);

        $this->json('POST', "/api/v1/volumes/{$id}/laserpoints/area", ['distance' => 50]);
        // Not enough manually annotated images for Delphi
        $this->assertResponseStatus(422);

        $this->makeManualAnnotations(1);
        $this->json('POST', "/api/v1/volumes/{$id}/laserpoints/area", ['distance' => 50]);
        // Images must have at least 2 laserpoint annotations
        $this->assertResponseStatus(422);
        Image::truncate();

        $this->makeManualAnnotations(5);
        $this->json('POST', "/api/v1/volumes/{$id}/laserpoints/area", ['distance' => 50]);
        // Images cant have more than 4 laserpoint annotations
        $this->assertResponseStatus(422);
        Image::truncate();

        $this->makeManualAnnotations(3);

        $this->expectsJobs(ProcessVolumeDelphiJob::class);
        $this->post("/api/v1/volumes/{$id}/laserpoints/area", ['distance' => 50]);
        $this->assertResponseOk();

        $this->makeManualAnnotations(2, 1);

        $this->json('POST', "/api/v1/volumes/{$id}/laserpoints/area", ['distance' => 50]);
        // Images don't have equal count of LP annotations
        $this->assertResponseStatus(422);
    }

    public function testComputeVolumeRemote()
    {
        $this->volume()->url = 'http://localhost';
        $this->volume()->save();
        $id = $this->volume()->id;
        $this->makeManualAnnotations(3);

        $this->beEditor();
        $this->doesntExpectJobs(ProcessVolumeDelphiJob::class);
        $this->json('POST', "/api/v1/volumes/{$id}/laserpoints/area", ['distance' => 50]);
        $this->assertResponseStatus(422);
    }

    protected function makeManualAnnotations($annotations, $images = 4)
    {
        $annotations = $annotations ?: rand(1, 10);
        $labelId = config('laserpoints.label_id');
        if (!Label::where('id', $labelId)->exists()) {
            LabelTest::create(['id' => $labelId, 'name' => 'Laserpoint']);
        }

        for ($i = 0; $i < $images; $i++) {
            $image = ImageTest::create([
                'volume_id' => $this->volume()->id,
                'filename' => uniqid(),
            ]);

            for ($j = 0; $j < $annotations; $j++) {
                $annotation = AnnotationTest::create([
                    'image_id' => $image->id,
                    'shape_id' => Shape::$pointId,
                ]);
                AnnotationLabelTest::create([
                    'annotation_id' => $annotation->id,
                    'label_id' => $labelId,
                ]);
            }
        }
    }
}
