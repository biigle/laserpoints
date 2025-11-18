<?php

namespace Biigle\Tests\Modules\Laserpoints\Http\Controllers\Api;

use ApiTestCase;
use Biigle\Image;
use Biigle\MediaType;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageAutomaticJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageManualJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeAutomaticJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeManualJob;
use Biigle\Shape;
use Biigle\Tests\ImageAnnotationLabelTest;
use Biigle\Tests\ImageAnnotationTest;
use Biigle\Tests\ImageTest;
use Biigle\Tests\LabelTest;
use Queue;

class LaserpointsControllerTest extends ApiTestCase
{
    public function testImageManual()
    {
        $label = LabelTest::create(['name' => 'Laser Point']);
        $image = ImageTest::create(['volume_id' => $this->volume()->id]);
        $this->doTestApiRoute('POST', "/api/v1/images/{$image->id}/laserpoints/manual");

        $this->beGuest();
        $this->post("/api/v1/images/{$image->id}/laserpoints/manual")
            ->assertStatus(403);

        $this->beEditor();

        // Distance is required.
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/manual", [
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        // Label is required.
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/manual", [
                'distance' => 50,
            ])
            ->assertStatus(422);

        Image::getQuery()->delete();
        $this->makeManualAnnotations($label, 1, 1);
        $image = Image::first();

        // Not enough manual annotations on this image.
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/manual", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        Image::getQuery()->delete();
        $this->makeManualAnnotations($label, 5, 1);
        $image = Image::first();

        // Too many manual annotations on this image.
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/manual", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        Image::getQuery()->delete();
        $this->makeManualAnnotations($label, 2, 1);
        $image = Image::first();

        $this->post("/api/v1/images/{$image->id}/laserpoints/manual", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(200);

        Queue::assertPushed(ProcessImageManualJob::class);
    }

    public function testImageManualTiled()
    {
        $this->markTestIncomplete('todo');
        $label = LabelTest::create(['name' => 'Laser Point']);
        $image = ImageTest::create(['tiled' => true, 'volume_id' => $this->volume()->id]);
        $this->makeManualAnnotations($label, 3);

        $this->beEditor();
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/manual", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(200);
        Queue::assertPushed(ProcessImageManualJob::class);
    }

    public function testImageAutomatic()
    {
        $image = ImageTest::create(['volume_id' => $this->volume()->id]);
        $this->doTestApiRoute('POST', "/api/v1/images/{$image->id}/laserpoints/automatic");

        $this->beGuest();
        $this->post("/api/v1/images/{$image->id}/laserpoints/automatic")
            ->assertStatus(403);

        $this->beEditor();

        // Distance is required.
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/automatic")
            ->assertStatus(422);

        $this->post("/api/v1/images/{$image->id}/laserpoints/automatic", [
                'distance' => 50,
            ])
            ->assertStatus(200);

        Queue::assertPushed(ProcessImageAutomaticJob::class);
    }

    public function testImageAutomaticTiled()
    {
        $image = ImageTest::create(['tiled' => true, 'volume_id' => $this->volume()->id]);

        $this->beEditor();
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/automatic", [
                'distance' => 50,
            ])
            ->assertStatus(422);
        Queue::assertNotPushed(ProcessImageAutomaticJob::class);
    }

    public function testVolumeManual()
    {
        $label = LabelTest::create(['name' => 'Laser Point']);
        $id = $this->volume()->id;
        $this->beEditor();

        // Missing distance
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/manual", [
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        // Missing label
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/manual", [
                'distance' => 50,
            ])
            ->assertStatus(422);

        $this->makeManualAnnotations($label, 1);

        // Images must have at least 2 laserpoint annotations
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/manual", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        Image::getQuery()->delete();
        $this->makeManualAnnotations($label, 5);
        // Images cant have more than 4 laserpoint annotations
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/manual", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        Image::getQuery()->delete();
        $this->makeManualAnnotations($label, 2, 1);
        $this->makeManualAnnotations($label, 3, 1);
        // Images don't have equal count of LP annotations
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/manual", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        Image::getQuery()->delete();
        $this->makeManualAnnotations($label, 3);
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/manual", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(200);
        Queue::assertPushed(ProcessVolumeManualJob::class);
    }

    public function testVolumeManualTiled()
    {
        $this->markTestIncomplete();
        $label = LabelTest::create(['name' => 'Laser Point']);
        $id = $this->volume()->id;
        $image = ImageTest::create(['tiled' => true, 'volume_id' => $id]);
        $this->makeManualAnnotations($label, 3);

        $this->beEditor();
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/manual", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(200);
        Queue::assertPushed(ProcessVolumeManualJob::class);
    }

    public function testVolumeManualVideo()
    {
        $label = LabelTest::create(['name' => 'Laser Point']);
        $id = $this->volume(['media_type_id' => MediaType::videoId()])->id;
        $this->beEditor();
        $this->makeManualAnnotations($label, 3);
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/manual", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);
    }

    public function testVolumeAutomatic()
    {
        $id = $this->volume()->id;
        $this->doTestApiRoute('POST', "/api/v1/volumes/{$id}/laserpoints/automatic");

        $this->beGuest();
        $this->post("/api/v1/volumes/{$id}/laserpoints/automatic")->assertStatus(403);

        $this->beEditor();
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/automatic", [
                'distance' => 50,
            ])
            ->assertStatus(200);

        $this->postJson("/api/v1/volumes/{$id}/laserpoints/automatic", [
                'distance' => 50,
                'disable_line_detection' => '1',
            ])
            ->assertStatus(200);
        Queue::assertPushed(ProcessVolumeAutomaticJob::class);
    }

    public function testVolumeAutomaticTiled()
    {
        $label = LabelTest::create(['name' => 'Laser Point']);
        $id = $this->volume()->id;
        $image = ImageTest::create(['tiled' => true, 'volume_id' => $id]);
        $this->makeManualAnnotations($label, 3);

        $this->beEditor();
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/automatic", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);
        Queue::assertNotPushed(ProcessVolumeAutomaticJob::class);
    }

    public function testVolumeAutomaticVideo()
    {
        $label = LabelTest::create(['name' => 'Laser Point']);
        $id = $this->volume(['media_type_id' => MediaType::videoId()])->id;
        $this->beEditor();
        $this->makeManualAnnotations($label, 3);
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/automatic", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);
    }

    protected function makeManualAnnotations($label, $annotations, $images = 4)
    {
        $annotations = $annotations ?: rand(1, 10);
        for ($i = 0; $i < $images; $i++) {
            $image = ImageTest::create([
                'volume_id' => $this->volume()->id,
                'filename' => uniqid(),
            ]);

            for ($j = 0; $j < $annotations; $j++) {
                $annotation = ImageAnnotationTest::create([
                    'image_id' => $image->id,
                    'shape_id' => Shape::pointId(),
                ]);
                ImageAnnotationLabelTest::create([
                    'annotation_id' => $annotation->id,
                    'label_id' => $label->id,
                ]);
            }
        }
    }
}
