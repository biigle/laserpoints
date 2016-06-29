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
    public static $laserpointsRules = [
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

    /**
     * Get the area attribute from the laserpoint detection
     *
     * @return float
     */
    public function getAreaAttribute()
    {
        $laserpoints = $this->laserpoints;
        if (is_array($laserpoints) && array_key_exists('area', $laserpoints)) {
            return $laserpoints['area'];
        }

        return null;
    }

    /**
     * Get the px attribute from the laserpoint detection
     *
     * @return int
     */
    public function getPxAttribute()
    {
        $laserpoints = $this->laserpoints;
        if (is_array($laserpoints) && array_key_exists('px', $laserpoints)) {
            return $laserpoints['px'];
        }

        return null;
    }

    /**
     * Get the count attribute from the laserpoint detection
     *
     * @return int
     */
    public function getCountAttribute()
    {
        $laserpoints = $this->laserpoints;
        if (is_array($laserpoints) && array_key_exists('count', $laserpoints)) {
            return $laserpoints['count'];
        }

        return null;
    }

    /**
     * Get the method attribute from the laserpoint detection
     *
     * @return string
     */
    public function getMethodAttribute()
    {
        $laserpoints = $this->laserpoints;
        if (is_array($laserpoints) && array_key_exists('method', $laserpoints)) {
            return $laserpoints['method'];
        }

        return null;
    }

    /**
     * Get the distance attribute from the laserpoint detection
     *
     * @return float
     */
    public function getDistanceAttribute()
    {
        $laserpoints = $this->laserpoints;
        if (is_array($laserpoints) && array_key_exists('distance', $laserpoints)) {
            return $laserpoints['distance'];
        }

        return null;
    }

    /**
     * Get the points attribute from the laserpoint detection
     *
     * @return array
     */
    public function getPointsAttribute()
    {
        $laserpoints = $this->laserpoints;
        if (is_array($laserpoints) && array_key_exists('points', $laserpoints)) {
            return $laserpoints['points'];
        }

        return null;
    }

    /**
     * Get the error attribute from the laserpoint detection
     *
     * @return bool
     */
    public function getErrorAttribute()
    {
        $laserpoints = $this->laserpoints;
        if (is_array($laserpoints) && array_key_exists('error', $laserpoints)) {
            return $laserpoints['error'];
        }

        return null;
    }
}
