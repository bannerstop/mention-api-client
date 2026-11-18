<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests\Fixtures;

use Nyholm\Psr7\Request;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;

/**
 * What a PSR-18 client throws when it refuses the request itself (not a network problem).
 */
final class RequestFailure extends \RuntimeException implements RequestExceptionInterface
{
    public function getRequest(): RequestInterface
    {
        return new Request('GET', 'https://mention-api.example.com');
    }
}
