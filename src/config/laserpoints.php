<?php

return [

    /*
    | Path to the Python executable.
    */
    'python' => '/usr/bin/python3',

    /*
    | Path to the manual detection script.
    */
    'manual_script' => __DIR__.'/../resources/scripts/manual.py',

    /*
    | Path to the automatic detection script.
    */
    'automatic_script' => __DIR__.'/../resources/scripts/automatic.py',

    /*
    | Directory for temporary files.
    */
    'tmp_dir' => sys_get_temp_dir(),

    /*
     | Specifies which queue should be used for which job.
     */
    'process_automatic_queue' => env('LASERPOINTS_PROCESS_AUTOMATIC_QUEUE', env('LASERPOINTS_PROCESS_DELPHI_QUEUE', 'default')),
    'process_manual_queue' => env('LASERPOINTS_PROCESS_MANUAL_QUEUE', 'default'),
];
