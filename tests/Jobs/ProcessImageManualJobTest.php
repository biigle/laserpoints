<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use Biigle\Modules\Laserpoints\Jobs\ProcessImageManualJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessManualJob;
use Biigle\Tests\ImageTest;
use Biigle\Tests\LabelTest;
use Queue;
use TestCase;

class ProcessImageManualJobTest extends TestCase
{
    public function testHandle()
    {
        $image = ImageTest::create();
        $label = LabelTest::create();
        Queue::fake();
        with(new ProcessImageManualJob($image, 50, $label->id))->handle();
        Queue::assertPushed(ProcessManualJob::class);
    }
}
