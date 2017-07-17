<?php

class Psr6CacheAdapterTest extends \Cache\IntegrationTests\CachePoolTest
{
    public function createCachePool()
    {
        $psr16 = \WildWolf\Psr16MemoryCache::instance();
        $psr6  = new \WildWolf\Psr6CacheAdapter($psr16);
        return $psr6;
    }
}
