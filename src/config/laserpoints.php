<?php

return [

    /*
    | Path to the Python executable.
    */
    'python' => '/usr/bin/python',

    /*
    | Path to the detect script.
    */
    'detect_script' => __DIR__.'/../resources/scripts/detect.py',

    /*
    | Path to the Delphi gather script.
    */
    'delphi_gather_script' => __DIR__.'/../resources/scripts/delphi_gather.py',

    /*
    | Path to the Delphi gather finish script.
    */
    'delphi_gather_finish_script' => __DIR__.'/../resources/scripts/delphi_gather_finish.py',

    /*
    | Path to the Delphi apply script.
    */
    'delphi_apply_script' => __DIR__.'/../resources/scripts/delphi_apply.py',

    /*
    | Directory for temporary files to share data between workers. Note that this
    | directory must be accessible for all workers! The storage directory might be a
    | good idea.
    */
    'tmp_dir' => storage_path('framework/cache/laserpoints'),
];
