<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests\Fixtures;

use Psr\SimpleCache\CacheInterface;

// psr/simple-cache 3 has return types (PHP 8.0+), versions 1 and 2 have none. A class that has to run with all of
// them needs two bodies, and the typed one cannot even be parsed by PHP 7.4, so each lives in its own file.
require (new \ReflectionMethod(CacheInterface::class, 'get'))->hasReturnType() ? __DIR__ . '/ArrayCacheTyped.php' : __DIR__ . '/ArrayCacheUntyped.php';
