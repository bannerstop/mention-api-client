<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Http;

use GuzzleHttp\ClientInterface as GuzzleClient;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Applies the timeout of MentionApiConfig to a Guzzle client. Guzzle has no timeout unless it is told one.
 *
 * MentionApiClient wraps a Guzzle 7 client that you pass to it in this class by itself; you can also create it
 * yourself.
 */
final class GuzzleTimeoutClient implements ClientInterface, TimeoutAware
{
    /** @var GuzzleClient */
    private $client;
    /** @var float|null */
    private $timeout;

    public function __construct(GuzzleClient $client, ?float $timeout = null)
    {
        $this->client = $client;
        $this->timeout = $timeout;
    }

    public function withTimeout(float $seconds): ClientInterface
    {
        return new self($this->client, $seconds);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        // The same options Guzzle 7 uses for sendRequest(): every status is an answer, and a redirect is left to the caller.
        $options = [RequestOptions::HTTP_ERRORS => false, RequestOptions::ALLOW_REDIRECTS => false];
        if (null !== $this->timeout) {
            $options[RequestOptions::TIMEOUT] = $this->timeout;
        }

        return $this->client->send($request, $options);
    }
}
