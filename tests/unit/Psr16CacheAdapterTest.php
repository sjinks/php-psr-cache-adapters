<?php

class Psr16CacheAdapterTest extends \Cache\IntegrationTests\SimpleCacheTest
{
    public function createSimpleCache()
    {
        $psr6  = \WildWolf\Psr6MemoryCache::instance();
        $psr16 = new \WildWolf\Psr16CacheAdapter($psr6);
        return $psr16;
    }
}
