<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A PSR-18 client on top of a Symfony HttpClient that applies the timeout of MentionApiConfig. Pass this instead of a
 * Psr18Client: a Psr18Client does not let go of the client it wraps, so the timeout cannot be set on it afterwards.
 *
 * Symfony's own "timeout" option only limits the wait for the next data and a transfer that keeps trickling in never
 * ends, so the timeout is also set as "max_duration", the limit for the whole call.
 */
final class SymfonyTimeoutClient implements ClientInterface, TimeoutAware
{
    /** @var HttpClientInterface */
    private $client;
    /** @var float|null */
    private $timeout;
    /** @var ResponseFactoryInterface|null */
    private $responseFactory;
    /** @var StreamFactoryInterface|null */
    private $streamFactory;
    /** @var Psr18Client|null */
    private $psr18 = null;

    /**
     * @param ResponseFactoryInterface|null $responseFactory PSR-17 factories for the answers, found by Psr18Client when left out
     */
    public function __construct(HttpClientInterface $client, ?float $timeout = null, ?ResponseFactoryInterface $responseFactory = null, ?StreamFactoryInterface $streamFactory = null)
    {
        $this->client = $client;
        $this->timeout = $timeout;
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
    }

    public function withTimeout(float $seconds): ClientInterface
    {
        return new self($this->client, $seconds, $this->responseFactory, $this->streamFactory);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if (null === $this->psr18) {
            $client = null === $this->timeout ? $this->client : $this->client->withOptions(['timeout' => $this->timeout, 'max_duration' => $this->timeout]);
            $this->psr18 = new Psr18Client($client, $this->responseFactory, $this->streamFactory);
        }

        return $this->psr18->sendRequest($request);
    }
}
