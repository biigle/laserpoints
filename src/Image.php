<?php

namespace Biigle\Modules\Laserpoints;

use DB;
use Exception;
use Biigle\Shape;
use Biigle\Image as BaseImage;

/**
 * Extends the base Biigle image.
 */
class Image extends BaseImage
{
    /**
     * Name of the attribute that stores the laserpoints information in the image
     * 'attrs' object.
     *
     * @var string
     */
    const LASERPOINTS_ATTRIBUTE = 'laserpoints';

    /**
     * Minimum number of required manual laserpoint annotations per image.
     *
     * @var int
     */
    const MIN_MANUAL_POINTS = 2;

    /**
     * Maximum number of supported manual laserpoint annotations per image.
     *
     * @var int
     */
    const MAX_MANUAL_POINTS = 4;

    /**
     * Validation rules for a new laserpoint computation.
     *
     * @var array
     */
    public static $laserpointsRules = [
        'distance' => 'required|numeric|min:1',
    ];

    /**
     * Properties of the laserpoints object.
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
        'message',
    ];

    /**
     * Converts a regular Biigle image to a Laserpoints image.
     *
     * @param BaseImage $image Regular Biigle image instance
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
    }

    /**
     * Set or update the dynamic attribute for the laserpoints information.
     *
     * @param array $value The value to set
     */
    public function setLaserpointsAttribute($value)
    {
        if (!is_array($value) && !is_null($value)) {
            throw new Exception('Laserpoints information must be an array!');
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
     * Get the area attribute from the laserpoint detection.
     *
     * @return float
     */
    public function getAreaAttribute()
    {
        return $this->accessLaserpointsArray('area');
    }

    /**
     * Get the px attribute from the laserpoint detection.
     *
     * @return int
     */
    public function getPxAttribute()
    {
        return $this->accessLaserpointsArray('px');
    }

    /**
     * Get the count attribute from the laserpoint detection.
     *
     * @return int
     */
    public function getCountAttribute()
    {
        return $this->accessLaserpointsArray('count');
    }

    /**
     * Get the method attribute from the laserpoint detection.
     *
     * @return string
     */
    public function getMethodAttribute()
    {
        return $this->accessLaserpointsArray('method');
    }

    /**
     * Get the distance attribute from the laserpoint detection.
     *
     * @return float
     */
    public function getDistanceAttribute()
    {
        return $this->accessLaserpointsArray('distance');
    }

    /**
     * Get the points attribute from the laserpoint detection.
     *
     * @return array
     */
    public function getPointsAttribute()
    {
        return $this->accessLaserpointsArray('points');
    }

    /**
     * Get the error attribute from the laserpoint detection.
     *
     * @return bool
     */
    public function getErrorAttribute()
    {
        return $this->accessLaserpointsArray('error');
    }

    /**
     * Get the message attribute from the laserpoint detection.
     *
     * @return bool
     */
    public function getMessageAttribute()
    {
        return $this->accessLaserpointsArray('message');
    }

    /**
     * Determines if this image has a valid number of manually annotated laserpoints.
     *
     * @param Image $image
     * @throws Exception If the image has an invalid count of manually annotated laserpoints
     *
     * @return bool
     */
    public function readyForManualDetection()
    {
        $labelId = config('laserpoints.label_id');
        $count = DB::table('annotations')
            ->join('annotation_labels', 'annotation_labels.annotation_id', '=', 'annotations.id')
            ->where('annotations.image_id', $this->id)
            ->where('annotation_labels.label_id', $labelId)
            ->where('annotations.shape_id', Shape::$pointId)
            ->count();

        if ($count > 0) {
            if ($count < self::MIN_MANUAL_POINTS) {
                throw new Exception('An image must have at least '.self::MIN_MANUAL_POINTS.' manually annotated laserpoints (has '.$count.').');
            } elseif ($count > self::MAX_MANUAL_POINTS) {
                throw new Exception('An image can\'t have more than '.self::MAX_MANUAL_POINTS.' manually annotated laserpoints (has '.$count.').');
            }
        }

        return $count > 0;
    }

    /**
     * Get an attribute from the laserpoints array.
     *
     * @param  string $key
     * @return mixed
     */
    protected function accessLaserpointsArray($key)
    {
        $laserpoints = $this->laserpoints;
        if (is_array($laserpoints) && array_key_exists($key, $laserpoints)) {
            return $laserpoints[$key];
        }
    }
}
