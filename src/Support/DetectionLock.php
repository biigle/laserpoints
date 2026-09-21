<?php

namespace Biigle\Modules\Laserpoints\Support;

use Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;

/**
 * Tracks running laser point detections of a volume to prevent concurrent submissions.
 *
 * The state of a volume is stored in a single cache entry so a volume submission
 * doesn't have to check the entries of all images of the volume.
 */
class DetectionLock
{
    /**
     * Try to mark a volume detection as running.
     *
     * @param int $volumeId
     *
     * @return bool Whether the lock was acquired.
     */
    public static function acquireVolume(int $volumeId): bool
    {
        try {
            return static::mutate($volumeId, function (array &$state) {
                if ($state['volume'] || !empty($state['images'])) {
                    return false;
                }

                $state['volume'] = true;

                return true;
            });
        } catch (LockTimeoutException $e) {
            return false;
        }
    }

    /**
     * Mark a volume detection as finished.
     *
     * @param int $volumeId
     */
    public static function releaseVolume(int $volumeId): void
    {
        static::mutate($volumeId, function (array &$state) {
            $state['volume'] = false;

            return true;
        });
    }

    /**
     * Try to mark a single image detection as running.
     *
     * @param int $volumeId
     * @param int $imageId
     *
     * @return bool Whether the lock was acquired.
     */
    public static function acquireImage(int $volumeId, int $imageId): bool
    {
        try {
            return static::mutate($volumeId, function (array &$state) use ($imageId) {
                if ($state['volume'] || array_key_exists($imageId, $state['images'])) {
                    return false;
                }

                $state['images'][$imageId] = true;

                return true;
            });
        } catch (LockTimeoutException $e) {
            return false;
        }
    }

    /**
     * Mark a single image detection as finished.
     *
     * @param int $volumeId
     * @param int $imageId
     */
    public static function releaseImage(int $volumeId, int $imageId): void
    {
        static::mutate($volumeId, function (array &$state) use ($imageId) {
            unset($state['images'][$imageId]);

            return true;
        });
    }

    /**
     * Get the cache key of the state of a volume.
     *
     * @param int $volumeId
     *
     * @return string
     */
    public static function key(int $volumeId): string
    {
        return "laserpoints-volume-{$volumeId}";
    }

    /**
     * Atomically read, modify and write the state of a volume.
     *
     * @param int $volumeId
     * @param callable $callback Receives the state by reference. The state is only
     * written if the callback returns true.
     *
     * @throws LockTimeoutException
     *
     * @return bool Return value of the callback.
     */
    protected static function mutate(int $volumeId, callable $callback): bool
    {
        $key = static::key($volumeId);

        $wait = config('laserpoints.lock_wait');

        return Cache::lock("{$key}-mutex", 10)->block($wait, function () use ($key, $callback) {
            $state = Cache::get($key, ['volume' => false, 'images' => []]);

            if ($callback($state) !== true) {
                return false;
            }

            if (!$state['volume'] && empty($state['images'])) {
                Cache::forget($key);
            } else {
                // The TTL is a safety net for jobs that were killed without releasing
                // the lock.
                Cache::put($key, $state, config('laserpoints.lock_ttl'));
            }

            return true;
        });
    }
}
