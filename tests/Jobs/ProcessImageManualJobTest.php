<?php

namespace Biigle\Tests\Modules\Laserpoints\Jobs;

use Queue;
use TestCase;
use Biigle\Tests\ImageTest;
use Biigle\Tests\LabelTest;
use Biigle\Modules\Laserpoints\Jobs\ProcessManualJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageManualJob;

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
