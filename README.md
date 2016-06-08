# Dias Laserpoints Module

Install the module:

Add the following to the repositories array of your `composer.json`:
```
{
  "type": "vcs",
  "url": "https://github.com/BiodataMiningGroup/dias-laserpoints.git"
}
```

1. Run `php composer.phar require dias/laserpoints:dev-master`.
2. Add `'Dias\Modules\Laserpoints\LaserpointsServiceProvider'` to the `providers` array in `config/app.php`.
3. Run `php artisan laserpoints:publish` to refresh the public assets of this package. Do this for every update of the package.
