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
     * Name of the attribute that stores the laser points information in the image
     * 'attrs' object.
     *
     * @var string
     */
    const LASERPOINTS_ATTRIBUTE = 'laserpoints';

    /**
     * Minimum number of required manual laser point annotations per image.
     *
     * @var int
     */
    const MIN_MANUAL_POINTS = 2;

    /**
     * Maximum number of supported manual laser point annotations per image.
     *
     * @var int
     */
    const MAX_MANUAL_POINTS = 4;

    /**
     * Validation rules for a new laser point computation.
     *
     * @var array
     */
    public static $laserpointsRules = [
        'distance' => 'required|numeric|min:1',
    ];

    /**
     * Properties of the laser points object.
     *
     * @var array
     */
    private static $infoParams = [
        'area',
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
     * Return the dynamic attribute for the laser points information.
     *
     * @return array
     */
    public function getLaserpointsAttribute()
    {
        return $this->getJsonAttr(self::LASERPOINTS_ATTRIBUTE);
    }

    /**
     * Set or update the dynamic attribute for the laser points information.
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

        $this->setJsonAttr(self::LASERPOINTS_ATTRIBUTE, $value);
    }

    /**
     * Get the area attribute from the laser point detection.
     *
     * @return float
     */
    public function getAreaAttribute()
    {
        return $this->accessLaserpointsArray('area');
    }

    /**
     * Get the count attribute from the laser point detection.
     *
     * @return int
     */
    public function getCountAttribute()
    {
        return $this->accessLaserpointsArray('count');
    }

    /**
     * Get the method attribute from the laser point detection.
     *
     * @return string
     */
    public function getMethodAttribute()
    {
        return $this->accessLaserpointsArray('method');
    }

    /**
     * Get the distance attribute from the laser point detection.
     *
     * @return float
     */
    public function getDistanceAttribute()
    {
        return $this->accessLaserpointsArray('distance');
    }

    /**
     * Get the points attribute from the laser point detection.
     *
     * @return array
     */
    public function getPointsAttribute()
    {
        return $this->accessLaserpointsArray('points');
    }

    /**
     * Get the error attribute from the laser point detection.
     *
     * @return bool
     */
    public function getErrorAttribute()
    {
        return $this->accessLaserpointsArray('error');
    }

    /**
     * Get the message attribute from the lase point detection.
     *
     * @return bool
     */
    public function getMessageAttribute()
    {
        return $this->accessLaserpointsArray('message');
    }

    /**
     * Determines if this image has a valid number of manually annotated laser points.
     *
     * @param Image $image
     * @throws Exception If the image has an invalid count of manually annotated laser points
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
            ->where('annotations.shape_id', Shape::pointId())
            ->count();

        if ($count > 0) {
            if ($count < self::MIN_MANUAL_POINTS) {
                throw new Exception('An image must have at least '.self::MIN_MANUAL_POINTS.' manually annotated laser points (has '.$count.').');
            } elseif ($count > self::MAX_MANUAL_POINTS) {
                throw new Exception('An image can\'t have more than '.self::MAX_MANUAL_POINTS.' manually annotated laser points (has '.$count.').');
            }
        }

        return $count > 0;
    }

    /**
     * Get an attribute from the laser points array.
     *
     * @param  string $key
     * @return mixed
     */
    protected function accessLaserpointsArray($key)
    {
        return array_get($this->laserpoints, $key);
    }
}
