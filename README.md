# BIIGLE Laserpoints Module

[![Test status](https://github.com/biigle/laserpoints/workflows/Tests/badge.svg)](https://github.com/biigle/laserpoints/actions?query=workflow%3ATests)

This is the BIIGLE module to perform a heuristic laser point detection on images.

## Installation

This module is already included in [`biigle/biigle`](https://github.com/biigle/biigle).

1. Run `composer require biigle/laserpoints`.
2. Add `Biigle\Modules\Laserpoints\LaserpointsServiceProvider::class` to the `providers` array in `config/app.php`.
3. Run `php artisan vendor:publish --tag=public` to publish the public assets of this module.
4. Run `pip install -r vendor/biigle/laserpoints/requirements.txt` to install the Python requirements.
5. Configure a storage disk for the temporary laserpoints files `LASERPOINTS_DISK` variable to the name of this storage disk in the `.env` file. Example for a local disk:
    ```php
    'laserpoints' => [
        'driver' => 'local',
        'root' => storage_path('framework/cache/laserpoints'),
    ],
    ```

## References

Reference publications that you should cite if you use the laser point detection for one of your studies.

- **BIIGLE 2.0**
    [Langenkämper, D., Zurowietz, M., Schoening, T., & Nattkemper, T. W. (2017). Biigle 2.0-browsing and annotating large marine image collections.](https://doi.org/10.3389/fmars.2017.00083)
    Frontiers in Marine Science, 4, 83. doi: `10.3389/fmars.2017.00083`

- **Laser Point Detection**
    [Schoening, T., Kuhn, T., Bergmann, M., & Nattkemper, T. W. (2015). DELPHI—fast and adaptive computational laser point detection and visual footprint quantification for arbitrary underwater image collections.](https://doi.org/10.3389/fmars.2015.00020)
    Frontiers in Marine Science, 2, 20. doi: `10.3389/fmars.2015.00020`

## Developing

Take a look at the [development guide](https://github.com/biigle/core/blob/master/DEVELOPING.md) of the core repository to get started with the development setup.

Want to develop a new module? Head over to the [biigle/module](https://github.com/biigle/module) template repository.

## Contributions and bug reports

Contributions to BIIGLE are always welcome. Check out the [contribution guide](https://github.com/biigle/core/blob/master/CONTRIBUTING.md) to get started.
