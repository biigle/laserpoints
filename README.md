# BIIGLE Laserpoints Module

This is the BIIGLE module to perform a heuristic laser point detection on images.

## Installation

1. Run `composer config repositories.laserpoints vcs git@github.com:biigle/laserpoints.git`
2. Run `composer require biigle/laserpoints`.
3. Add `Biigle\Modules\Laserpoints\LaserpointsServiceProvider::class` to the `providers` array in `config/app.php`.
4. Run `php artisan vendor:publish --tag=public` to publish the public assets of this module.
5. Run `pip install -r vendor/biigle/laserpoints/requirements.txt` to install the Python requirements.

## Developing

Take a look at the [development guide](https://github.com/biigle/core/blob/master/DEVELOPING.md) of the core repository to get started with the development setup.

Want to develop a new module? Head over to the [biigle/module](https://github.com/biigle/module) template repository.

## Contributions and bug reports

Contributions to BIIGLE are always welcome. Check out the [contribution guide](https://github.com/biigle/core/blob/master/CONTRIBUTING.md) to get started.
