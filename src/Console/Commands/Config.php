<?php

namespace Dias\Modules\Laserpoints\Console\Commands;

use Illuminate\Console\Command;
use Dias\Modules\Laserpoints\LaserpointsProvider as ServiceProvider;

class Config extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'laserpoints:config';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish the configuration of this package';

    /**
     * Execute the command.
     *
     * @return void
     */
    public function handle()
    {
        $this->call('vendor:publish', [
            '--provider' => ServiceProvider::class,
            '--tag' => ['config'],
        ]);
    }
}
