<?php

namespace Biigle\Tests\Modules\Laserpoints\Support;

use Biigle\Modules\Laserpoints\Support\LaserpointsScript;
use Exception;
use TestCase;

class LaserpointsScriptTest extends TestCase
{
    public function testExec()
    {
        $script = new LaserpointsScript;
        $json = json_encode(['error' => false, 'area' => 1.5]);

        $this->assertSame(
            ['error' => false, 'area' => 1.5],
            $script->exec('echo '.escapeshellarg($json))
        );
    }

    public function testExecIgnoresTrailingLogOutput()
    {
        $script = new LaserpointsScript;
        $json = json_encode(['error' => false, 'area' => 1.5]);
        // The detection scripts log to STDERR, which is merged into STDOUT by the
        // commands of this module. The JSON result is not necessarily the last line of
        // the output.
        $command = '(echo '.escapeshellarg($json).'; echo "INFO - Total execution time" >&2) 2>&1';

        $this->assertSame(
            ['error' => false, 'area' => 1.5],
            $script->exec($command)
        );
    }

    public function testExecIgnoresLeadingLogOutput()
    {
        $script = new LaserpointsScript;
        $json = json_encode(['error' => true, 'message' => 'Expected error.']);
        $command = '(echo "INFO - Using channel mode: red" >&2; echo '.escapeshellarg($json).') 2>&1';

        $this->assertSame(
            ['error' => true, 'message' => 'Expected error.'],
            $script->exec($command)
        );
    }

    public function testExecNoJsonOutput()
    {
        $script = new LaserpointsScript;
        $this->expectException(Exception::class);
        $script->exec('echo "INFO - Total execution time"');
    }

    public function testExecJsonWithoutErrorProperty()
    {
        $script = new LaserpointsScript;
        $this->expectException(Exception::class);
        $script->exec('echo '.escapeshellarg(json_encode(['area' => 1.5])));
    }

    public function testExecNonZeroExitCode()
    {
        $script = new LaserpointsScript;
        $this->expectException(Exception::class);
        $script->exec('echo '.escapeshellarg(json_encode(['error' => true])).'; exit 1');
    }

    public function testExecWithoutDecoding()
    {
        $script = new LaserpointsScript;
        $this->assertSame('done', $script->exec('echo "done"', decode: false));
    }
}
