<?php

namespace Dias\Modules\Laserpoints\Console\Commands;

use Illuminate\Console\Command;
use Dias\Attribute;
use Dias\Modules\Laserpoints\LaserpointsServiceProvider as ServiceProvider;

class Install extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'laserpoints:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the database migrations for the dias/laserpoints package';

    /**
     * Execute the command.
     *
     * @return void
     */
    public function handle()
    {
        $this->setUpMigration();
        $this->info('Finished! Please refer to the package readme on how to proceed.');
    }

    private function setUpMigration()
    {
        $this->call('vendor:publish', [
            '--provider' => ServiceProvider::class,
            '--tag' => ['migrations'],
        ]);
        $this->call('vendor:publish', [
            '--provider' => ServiceProvider::class,
            '--tag' => ['public'],
        ]);

        if ($this->confirm('Do you want to run the migration right away?')) {
            $this->call('migrate');
        }
    }
}
