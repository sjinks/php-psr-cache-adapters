<?php

namespace WildWolf;

/**
 * Adapts a PSR-6 Cache Interface (\Psr\Cache\CacheItemPoolInterface)
 * to the PSR-16 Cache Interface (\Psr\SimpleCache\CacheInterface)
 */
class Psr16CacheAdapter implements \Psr\SimpleCache\CacheInterface
{
    /**
     * @var \Psr\Cache\CacheItemPoolInterface
     */
    private $psr6;

    public function __construct(\Psr\Cache\CacheItemPoolInterface $psr6)
    {
        $this->psr6 = $psr6;
    }

    /**
     * Fetches a value from the cache.
     *
     * @param string $key     The unique key of this item in the cache.
     * @param mixed  $default Default value to return if the key does not exist.
     *
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function get($key, $default = null)
    {
        try {
            $item = $this->psr6->getItem($key);
            return $item->isHit() ? $item->get() : $default;
        } catch (\Psr\Cache\InvalidArgumentException $e) {
            throw new \WildWolf\Cache\InvalidArgumentException();
        }
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string                $key   The key of the item to store.
     * @param mixed                 $value The value of the item to store, must be serializable.
     * @param null|int|DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
     *                                     the driver supports TTL then the library may set a default value
     *                                     for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function set($key, $value, $ttl = null)
    {
        \WildWolf\Cache\Validator::validateTtl($ttl);

        try {
            $item = $this->psr6->getItem($key);
            $item->set($value);
            $item->expiresAfter($ttl);
            return $this->psr6->save($item);
        } catch (\Psr\Cache\InvalidArgumentException $e) {
            throw new \WildWolf\Cache\InvalidArgumentException();
        }
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function delete($key)
    {
        try {
            return $this->psr6->deleteItem($key);
        } catch (\Psr\Cache\InvalidArgumentException $e) {
            throw new \WildWolf\Cache\InvalidArgumentException();
        }
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear()
    {
        return $this->psr6->clear();
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys    A list of keys that can obtained in a single operation.
     * @param mixed    $default Default value to return for keys that do not exist.
     *
     * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function getMultiple($keys, $default = null)
    {
        \WildWolf\Cache\Validator::validateIterable($keys);

        $keys   = self::extractKeys($keys);
        $result = [];
        $values = null;
        try {
            $values = $this->psr6->getItems($keys);
        } catch (\Psr\Cache\InvalidArgumentException $e) {
            throw new \WildWolf\Cache\InvalidArgumentException();
        }

        foreach ($values as $key => $value) {
            $result[$key] = $value->isHit() ? $value->get() : $default;
            unset($values[$key]);
        }

        return $result;
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable              $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $values is neither an array nor a Traversable,
     *   or if any of the $values are not a legal value.
     */
    public function setMultiple($values, $ttl = null)
    {
        \WildWolf\Cache\Validator::validateIterable($values);
        \WildWolf\Cache\Validator::validateTtl($ttl);

        $keys = null;
        self::parseIterable($values, $keys, $values);

        $result = true;
        $items  = null;
        try {
            $items = $this->psr6->getItems($keys);
        } catch (\Psr\Cache\InvalidArgumentException $e) {
            throw new \WildWolf\Cache\InvalidArgumentException();
        }

        foreach ($items as $key => $value) {
            $value->set($values[$key]);
            $value->expiresAfter($ttl);
            $result = $result && $this->psr6->save($value);
        }

        return $result;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function deleteMultiple($keys)
    {
        \WildWolf\Cache\Validator::validateIterable($keys);
        $keys = self::extractKeys($keys);

        try {
            return $this->psr6->deleteItems($keys);
        } catch (\Psr\Cache\InvalidArgumentException $e) {
            throw new \WildWolf\Cache\InvalidArgumentException();
        }
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it making the state of your app out of date.
     *
     * @param string $key The cache item key.
     *
     * @return bool
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function has($key)
    {
        try {
            return $this->psr6->hasItem($key);
        } catch (\Psr\Cache\InvalidArgumentException $e) {
            throw new \WildWolf\Cache\InvalidArgumentException();
        }
    }

    private static function extractKeys($keys)
    {
        if (!is_array($keys)) {
            $k = [];
            foreach ($keys as $key) {
                $k[] = $key;
            }

            return $k;
        }

        return $keys;
    }

    private static function parseIterable($iterable, &$keys, &$vals)
    {
        $keys = [];
        $vals = [];
        foreach ($iterable as $key => $value) {
            if (is_int($key) || is_string($key)) {
                $keys[]     = (string)$key;
                $vals[$key] = $value;
            } else {
                throw new \WildWolf\Cache\InvalidArgumentException();
            }
        }
    }
}
