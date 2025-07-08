<?php

return [

    /*
    | Path to the Python executable.
    */
    'python' => '/usr/bin/python3',

    /*
    | Path to the detect script.
    */
    'manual_lp_footprint_computation_script' => __DIR__.'/../resources/scripts/manual_footprint.py',

    /*
    | Path to the Delphi apply script.
    */
    'automatic_lp_detection_script' => __DIR__.'/../resources/scripts/laser_point_detector.py',

    /*
    | Directory for temporary files.
    */
    'tmp_dir' => sys_get_temp_dir(),

    /*
    | Storage disk to store Delphi gather files that are shared by queued jobs.
    */
    'disk' => env('LASERPOINTS_STORAGE_DISK', 'laserpoints'),

    /*
     | Specifies which queue should be used for which job.
     */
    'process_delphi_queue' => env('LASERPOINTS_PROCESS_DELPHI_QUEUE', 'default'),
    'process_manual_queue' => env('LASERPOINTS_PROCESS_MANUAL_QUEUE', 'default'),
];
