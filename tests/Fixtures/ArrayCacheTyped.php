<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests\Fixtures;

use Psr\SimpleCache\CacheInterface;

// For psr/simple-cache 3 (return types, PHP 8.0+); ArrayCache.php picks the file.
final class ArrayCache implements CacheInterface
{
    /** @var array<string, array{0: mixed, 1: ?int}> */
    public array $entries = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->entries[$key][0] ?? $default;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $this->entries[$key] = [$value, $ttl instanceof \DateInterval ? null : $ttl];

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->entries[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->entries = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return isset($this->entries[$key]);
    }
}
