<?php

namespace Boshnik\FastPaginate;

use xPDO\xPDO;

class Cache
{
    public function __construct(
        private \modX $modx,
        private array $cacheOptions = [xPDO::OPT_CACHE_KEY => 'extra'],
        private int $cacheTime = 3600
    ) {}

    /**
     * Stores data in the cache.
     *
     * @param string $key Cache key.
     * @param mixed $data Data to store (can be any data type).
     * @param int|null $cacheTime Custom cache time (optional).
     * @return bool True if the cache was set successfully, false otherwise.
     */
    public function set(string $key, mixed $data, ?int $cacheTime = null): bool
    {
        $result = $this->modx->cacheManager->set($key, $data, $cacheTime ?? $this->cacheTime, $this->cacheOptions);

        if ($result === false) {
            $this->modx->log(\modX::LOG_LEVEL_ERROR, "Failed to set cache for key: {$key}");
        }

        return $result;
    }

    /**
     * Retrieves data from the cache.
     *
     * @param string $key Cache key.
     * @return mixed Cached data or false if not found.
     */
    public function get(string $key): mixed
    {
        $result = $this->modx->cacheManager->get($key, $this->cacheOptions);

        if ($result === false) {
            $this->modx->log(\modX::LOG_LEVEL_ERROR, "Failed to get cache for key: {$key}");
        }

        return $result;
    }

    /**
     * Deletes data from the cache.
     *
     * @param string $key Cache key.
     * @return bool True if the cache was deleted successfully, false otherwise.
     */
    public function delete(string $key): bool
    {
        if ($this->get($key) === false) {
            $this->modx->log(\modX::LOG_LEVEL_WARN, "Attempted to delete non-existent cache key: {$key}");
            return false;
        }

        $result = $this->modx->cacheManager->delete($key, $this->cacheOptions);

        if ($result === false) {
            $this->modx->log(\modX::LOG_LEVEL_ERROR, "Failed to delete cache for key: {$key}");
        }

        return $result;
    }

    /**
     * Clears the cache.
     *
     * Clears cache based on the provided cache key or refreshes all cache if none is specified.
     */
    public function clear()
    {
        $cacheKey = $this->cacheOptions[xPDO::OPT_CACHE_KEY] ?? null;

        if ($cacheKey) {
            $this->modx->cacheManager->refresh([$cacheKey => []]);
        } else {
            $this->modx->cacheManager->refresh();
        }

        return true;
    }
}