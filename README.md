# Biigle laser points Module

Install the module:

Add the following to the repositories array of your `composer.json`:
```
{
  "type": "vcs",
  "url": "https://github.com/BiodataMiningGroup/biigle-laserpoints.git"
}
```

1. Run `php composer.phar require biigle/laserpoints`.
2. Run `php artisan vendor:publish --tag=public` to refresh the public assets of this package. Do this for every update of the package.
3. Run `php artisan laserpoints:config` and open `config/laserpoints.php`. Set the ID of the global laser point label (`label_id`) and remove the script path (`script`) from the file to use the default path (unless you know what you are doing).
4. Run `pip install -r vendor/biigle/laserpoints/requirements.txt` to install python requirements.
