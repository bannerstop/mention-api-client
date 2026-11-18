<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests\Fixtures;

use Psr\SimpleCache\CacheInterface;

// For psr/simple-cache 1 and 2 (no return types, PHP 7.4 compatible); ArrayCache.php picks the file.
final class ArrayCache implements CacheInterface
{
    /** @var array<string, array{0: mixed, 1: ?int}> */
    public $entries = [];

    public function get($key, $default = null)
    {
        return $this->entries[$key][0] ?? $default;
    }

    public function set($key, $value, $ttl = null)
    {
        $this->entries[$key] = [$value, $ttl instanceof \DateInterval ? null : $ttl];

        return true;
    }

    public function delete($key)
    {
        unset($this->entries[$key]);

        return true;
    }

    public function clear()
    {
        $this->entries = [];

        return true;
    }

    public function getMultiple($keys, $default = null)
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function setMultiple($values, $ttl = null)
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple($keys)
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has($key)
    {
        return isset($this->entries[$key]);
    }
}
