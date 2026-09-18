<?php

namespace Biigle\Tests\Modules\Laserpoints\Support;

use Biigle\Modules\Laserpoints\Support\LaserpointsScript;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use TestCase;

class LaserpointsScriptTest extends TestCase
{
    public function testExec()
    {
        Process::fake([
            '*' => Process::result(json_encode(['error' => false, 'area' => 1.5])),
        ]);

        $script = new LaserpointsScript;

        $this->assertSame(
            ['error' => false, 'area' => 1.5],
            $script->exec(['python', 'script.py', '--input', 'my image.jpg'])
        );

        Process::assertRan(fn ($process) => $process->command === ['python', 'script.py', '--input', 'my image.jpg']);
    }

    public function testExecIgnoresErrorOutput()
    {
        // The detection scripts log to STDERR which must not interfere with the result.
        Process::fake([
            '*' => Process::result(
                output: json_encode(['error' => true, 'message' => 'Expected error.']),
                errorOutput: "INFO - Using channel mode: red\nINFO - Total execution time",
            ),
        ]);

        $script = new LaserpointsScript;

        $this->assertSame(
            ['error' => true, 'message' => 'Expected error.'],
            $script->exec(['python', 'script.py'])
        );
    }

    public function testExecNoJsonOutput()
    {
        // Suppress the expected Log::error() call so it doesn't pollute the log output.
        Log::spy();
        Process::fake(['*' => Process::result('INFO - Total execution time')]);
        $script = new LaserpointsScript;
        $this->expectException(Exception::class);
        $script->exec(['python', 'script.py']);
    }

    public function testExecJsonWithoutErrorProperty()
    {
        // Suppress the expected Log::error() call so it doesn't pollute the log output.
        Log::spy();
        Process::fake(['*' => Process::result(json_encode(['area' => 1.5]))]);
        $script = new LaserpointsScript;
        $this->expectException(Exception::class);
        $script->exec(['python', 'script.py']);
    }

    public function testExecNonZeroExitCode()
    {
        // Suppress the expected Log::error() call so it doesn't pollute the log output.
        Log::spy();
        Process::fake([
            '*' => Process::result(json_encode(['error' => true]), 'Traceback', 1),
        ]);
        $script = new LaserpointsScript;
        $this->expectException(Exception::class);
        $script->exec(['python', 'script.py']);
    }

    public function testExecWithoutDecoding()
    {
        Process::fake(['*' => Process::result("done\n", 'INFO - Saved color')]);
        $script = new LaserpointsScript;
        $this->assertSame('done', $script->exec(['python', 'script.py'], decode: false));
    }
}
