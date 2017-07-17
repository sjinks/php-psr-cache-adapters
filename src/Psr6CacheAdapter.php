<?php

namespace WildWolf;

class Psr6CacheAdapter implements \Psr\Cache\CacheItemPoolInterface
{
    /**
     * @var \Psr\SimpleCache\CacheInterface
     */
    private $psr16;

    public function __construct(\Psr\SimpleCache\CacheInterface $psr16)
    {
        $this->psr16 = $psr16;
    }

    /**
     * Returns a Cache Item representing the specified key.
     *
     * This method must always return a CacheItemInterface object, even in case of
     * a cache miss. It MUST NOT return null.
     *
     * @param string $key
     *   The key for which to return the corresponding Cache Item.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     *   If the $key string is not a legal value a \Psr\Cache\InvalidArgumentException
     *   MUST be thrown.
     *
     * @return \Psr\Cache\CacheItemInterface
     *   The corresponding Cache Item.
     */
    public function getItem($key)
    {
        try {
            $value  = $this->psr16->get($key, null);
            $result = new \WildWolf\Cache\CacheItem($key);

            if ($value !== null || $this->psr16->has($key)) {
                $result->set($value);
                $result->setIsHit(true);
            }

            return $result;
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            throw new \WildWolf\Cache\InvalidArgumentException();
        }
    }

    /**
     * Returns a traversable set of cache items.
     *
     * @param string[] $keys
     *   An indexed array of keys of items to retrieve.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     *   If any of the keys in $keys are not a legal value a \Psr\Cache\InvalidArgumentException
     *   MUST be thrown.
     *
     * @return array|\Traversable
     *   A traversable collection of Cache Items keyed by the cache keys of
     *   each item. A Cache item will be returned for each key, even if that
     *   key is not found. However, if no keys are specified then an empty
     *   traversable MUST be returned instead.
     */
    public function getItems(array $keys = array())
    {
        try {
            $result = [];
            $items  = $this->psr16->getMultiple($keys, null);

            foreach ($items as $key => $value) {
                $r = new \WildWolf\Cache\CacheItem($key);

                if ($value !== null || $this->psr16->has($key)) {
                    $r->setIsHit(true);
                    $r->set($value);
                }

                unset($items[$key]);
                $result[$key] = $r;
            }

            return $result;
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            throw new \WildWolf\Cache\InvalidArgumentException();
        }
    }

    /**
     * Confirms if the cache contains specified cache item.
     *
     * Note: This method MAY avoid retrieving the cached value for performance reasons.
     * This could result in a race condition with CacheItemInterface::get(). To avoid
     * such situation use CacheItemInterface::isHit() instead.
     *
     * @param string $key
     *   The key for which to check existence.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     *   If the $key string is not a legal value a \Psr\Cache\InvalidArgumentException
     *   MUST be thrown.
     *
     * @return bool
     *   True if item exists in the cache, false otherwise.
     */
    public function hasItem($key)
    {
        try {
            return $this->psr16->has($key);
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            throw new \WildWolf\Cache\InvalidArgumentException();
        }
    }

    /**
     * Deletes all items in the pool.
     *
     * @return bool
     *   True if the pool was successfully cleared. False if there was an error.
     */
    public function clear()
    {
        return $this->psr16->clear();
    }

    /**
     * Removes the item from the pool.
     *
     * @param string $key
     *   The key to delete.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     *   If the $key string is not a legal value a \Psr\Cache\InvalidArgumentException
     *   MUST be thrown.
     *
     * @return bool
     *   True if the item was successfully removed. False if there was an error.
     */
    public function deleteItem($key)
    {
        try {
            return $this->psr16->delete($key);
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            throw new \WildWolf\Cache\InvalidArgumentException();
        }
    }

    /**
     * Removes multiple items from the pool.
     *
     * @param string[] $keys
     *   An array of keys that should be removed from the pool.

     * @throws \Psr\Cache\InvalidArgumentException
     *   If any of the keys in $keys are not a legal value a \Psr\Cache\InvalidArgumentException
     *   MUST be thrown.
     *
     * @return bool
     *   True if the items were successfully removed. False if there was an error.
     */
    public function deleteItems(array $keys)
    {
        try {
            return $this->psr16->deleteMultiple($keys);
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            throw new \WildWolf\Cache\InvalidArgumentException();
        }
    }

    /**
     * Persists a cache item immediately.
     *
     * @param \Psr\Cache\CacheItemInterface $item
     *   The cache item to save.
     *
     * @return bool
     *   True if the item was successfully persisted. False if there was an error.
     */
    public function save(\Psr\Cache\CacheItemInterface $item)
    {
        try {
            if ($item instanceof \WildWolf\Cache\CacheItem) {
                $when = $item->expires();
                if ($when !== null) {
                    $now  = new \DateTime();
                    $diff = $now->diff($when);
                    return $this->psr16->set($item->getKey(), $item->get(), $diff);
                }
            }

            return $this->psr16->set($item->getKey(), $item->get(), null);
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            throw new \WildWolf\Cache\InvalidArgumentException();
        }
    }

    /**
     * Sets a cache item to be persisted later.
     *
     * @param \Psr\Cache\CacheItemInterface $item
     *   The cache item to save.
     *
     * @return bool
     *   False if the item could not be queued or if a commit was attempted and failed. True otherwise.
     */
    public function saveDeferred(\Psr\Cache\CacheItemInterface $item)
    {
        return $this->save($item);
    }

    /**
     * Persists any deferred cache items.
     *
     * @return bool
     *   True if all not-yet-saved items were successfully saved or there were none. False otherwise.
     */
    public function commit()
    {
        return true;
    }
}
