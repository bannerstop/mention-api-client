<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Http;

use Psr\Http\Client\ClientInterface;

/**
 * PSR-18 has no way to set a timeout. An HTTP client (or a thin decorator around it) that implements this interface
 * receives the timeout of MentionApiConfig; other clients keep the timeout they were configured with.
 */
interface TimeoutAware
{
    /**
     * A client that gives up after the given number of seconds without an answer.
     */
    public function withTimeout(float $seconds): ClientInterface;
}
