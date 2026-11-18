<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests\Fixtures;

use Bannerstop\MentionApi\Http\TimeoutAware;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client that can be told its timeout; records the timeout each request was sent with.
 */
final class TimeoutAwareHttpClient implements ClientInterface, TimeoutAware
{
    /** @var list<float|null> */
    public array $timeouts = [];

    private ClientInterface $inner;
    private ?float $timeout;

    public function __construct(ClientInterface $inner, ?float $timeout = null)
    {
        $this->inner = $inner;
        $this->timeout = $timeout;
    }

    public function withTimeout(float $seconds): ClientInterface
    {
        $clone = clone $this;
        $clone->timeout = $seconds;
        $clone->timeouts = &$this->timeouts;

        return $clone;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->timeouts[] = $this->timeout;

        return $this->inner->sendRequest($request);
    }
}
