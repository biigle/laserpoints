# Biigle laser points Module

Install the module:

Add the following to the repositories array of your `composer.json`:
```
{
  "type": "vcs",
  "url": "https://github.com/biigle/laserpoints.git"
}
```

1. Run `php composer.phar require biigle/laserpoints`.
2. Add `'Biigle\Modules\Laserpoints\LaserpointsServiceProvider'` to the `providers` array in `config/app.php`.
3. Run `php artisan laserpoints:publish` to refresh the public assets of this package. Do this for every update of the package.
4. Run `pip install -r vendor/biigle/laserpoints/requirements.txt` to install python requirements.
