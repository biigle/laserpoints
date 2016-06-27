<?php

namespace Dias\Modules\Laserpoints;

use Dias\Image as BaseImage;
use Exception;

/**
 * Extends the base Dias image
 */
class Image extends BaseImage {

    /**
     * Name of the attribute that stores the laserpoints information in the image
     * 'attrs' object.
     *
     * @var string
     */
    const LASERPOINTS_ATTRIBUTE = 'laserpoints';

    /**
     * Validation rules for a new laserpoint computation
     *
     * @var array
     */
    private static $laserpointsRules = [
        'distance' => 'required|numeric',
    ];

    /**
     * Properties of the laserpoints object
     *
     * @var array
     */
    private static $infoParams = [
        'area',
        'px',
        'count',
        'method',
        'distance',
        'points',
        'error',
    ];

    /**
     * Converts a regular Dias image to a Laserpoints image
     *
     * @param BaseImage $image Regular Dias image instance
     *
     * @return Image
     */
    public static function convert(BaseImage $image)
    {
        $instance = new static;
        $instance->setRawAttributes($image->attributes);
        $instance->exists = $image->exists;
        return $instance->setRelations($image->relations);
    }

    /**
     * Return the dynamic attribute for the laserpoints information.
     *
     * @return array
     */
    public function getLaserpointsAttribute()
    {
        $attrs = $this->attrs;
        if (is_array($attrs) && array_key_exists(self::LASERPOINTS_ATTRIBUTE, $attrs)) {
            return $this->attrs[self::LASERPOINTS_ATTRIBUTE];
        }

        return null;
    }

    /**
     * Set or update the dynamic attribute for the laserpoints information.
     *
     * @param array $value The value to set
     */
    public function setLaserpointsAttribute($value)
    {
        if (!is_array($value) && !is_null($value)) {
            throw new Exception("Laserpoints information must be an array!");
        }

        if (!is_null($value)) {
            $value = array_only($value, static::$infoParams);
        }

        $attrs = $this->attrs;

        if ($value === null) {
            unset($attrs[self::LASERPOINTS_ATTRIBUTE]);
        } else {
            $attrs[self::LASERPOINTS_ATTRIBUTE] = $value;
        }

        $this->attrs = $attrs;
    }
}
