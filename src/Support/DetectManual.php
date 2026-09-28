<?php

namespace Biigle\Modules\Laserpoints\Support;

/**
 * Computation of the image footprint area based on manually annotated laser points.
 *
 * The computation is purely geometric and requires nothing but the image dimensions,
 * the real world distance of the laser points and their pixel coordinates. In
 * particular it does not require access to the image file.
 */
class DetectManual
{
    /**
     * Name of the detection method that is stored with the results.
     *
     * @var string
     */
    const METHOD = 'manual';

    /**
     * Execute a new manual laser point detection.
     *
     * @param int $width Width of the image in pixels
     * @param int $height Height of the image in pixels
     * @param float $distance Distance of the laser points in cm
     * @param array $points Coordinates of all manually annotated laser points on the
     * image as array of `[x, y]` pairs (like `[[100, 100], [200, 200]]`)
     *
     * @return array The detection result
     */
    public function execute($width, $height, $distance, array $points)
    {
        if (!$width || !$height) {
            return $this->error('The dimensions of the image are unknown.');
        }

        $count = count($points);
        // Distance of the laser points in m.
        $laserDistance = floatval($distance) / 100.0;
        $imageArea = floatval($width) * floatval($height);

        switch ($count) {
            case 4:
                // The laser points span a square with the edge length of the laser
                // distance. Its pixel area is estimated by the mean of the four
                // shortest distances between the points (i.e. the four edges).
                $distances = $this->pairwiseDistances($points);
                sort($distances);
                $pixelArea = pow(array_sum(array_slice($distances, 0, 4)) / 4.0, 2);
                $realArea = pow($laserDistance, 2);
                break;

            case 3:
                // The laser points span an equilateral triangle with the edge length of
                // the laser distance. Both areas are computed with Heron's formula.
                $a = $this->distance($points[0], $points[1]);
                $b = $this->distance($points[1], $points[2]);
                $c = $this->distance($points[0], $points[2]);

                $s = 1.5 * $laserDistance;
                $realArea = sqrt($s * pow($s - $laserDistance, 3));

                $s = ($a + $b + $c) / 2.0;
                $pixelArea = sqrt($s * ($s - $a) * ($s - $b) * ($s - $c));
                break;

            case 2:
                // The laser points span a line with the length of the laser distance.
                // Both "areas" are the squares of the respective lengths.
                $pixelArea = pow($this->distance($points[0], $points[1]), 2);
                $realArea = pow($laserDistance, 2);
                break;

            default:
                return $this->error('Unsupported number of laserpoints.');
        }

        if (!is_finite($pixelArea) || $pixelArea <= 0) {
            return $this->error('Computed pixel area is zero.');
        }

        $area = $realArea * $imageArea / $pixelArea;

        if (!is_finite($area) || $area <= 0) {
            return $this->error('The estimated image area is too small (was '.round($area).' sqm).');
        }

        return [
            'error' => false,
            'area' => $area,
            'count' => $count,
            'method' => self::METHOD,
            // The coordinates are flipped in the output. This is kept for backwards
            // compatibility with the results of the previous detection script.
            'points' => array_map(fn ($point) => [$point[1], $point[0]], $points),
        ];
    }

    /**
     * Euclidean distance between two points.
     *
     * @param array $a Point as `[x, y]` pair
     * @param array $b Point as `[x, y]` pair
     *
     * @return float
     */
    protected function distance(array $a, array $b)
    {
        return sqrt(pow(floatval($a[0]) - floatval($b[0]), 2) + pow(floatval($a[1]) - floatval($b[1]), 2));
    }

    /**
     * Euclidean distances between all pairs of the given points.
     *
     * @param array $points Points as array of `[x, y]` pairs
     *
     * @return array
     */
    protected function pairwiseDistances(array $points)
    {
        $distances = [];
        $count = count($points);

        for ($i = 0; $i < $count - 1; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $distances[] = $this->distance($points[$i], $points[$j]);
            }
        }

        return $distances;
    }

    /**
     * Assemble an error result.
     *
     * @param string $message
     *
     * @return array
     */
    protected function error($message)
    {
        return [
            'error' => true,
            'message' => $message,
            'method' => self::METHOD,
        ];
    }
}
