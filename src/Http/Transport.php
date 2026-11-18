<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Http;

use Bannerstop\MentionApi\Auth\Authenticator;
use Bannerstop\MentionApi\MentionApiConfig;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Sends one call to /v2 and reads the answer.
 *
 * @internal
 */
final class Transport
{
    private MentionApiConfig $config;
    private ClientInterface $http;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private Authenticator $authenticator;

    public function __construct(
        MentionApiConfig $config,
        ClientInterface $http,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        Authenticator $authenticator
    ) {
        $this->config = $config;
        $this->http = $http;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->authenticator = $authenticator;
    }

    /**
     * @param array<string, mixed>|null $body
     */
    public function send(string $method, string $path, ?array $body = null): ApiResponse
    {
        $request = $this->requestFactory->createRequest($method, $this->config->getBaseUrl() . $path)
            ->withHeader('Authorization', $this->authenticator->getAuthorizationHeader())
            ->withHeader('Accept', 'application/json');
        if (null !== $body) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream(json_encode($body, JSON_THROW_ON_ERROR)));
        }

        $response = $this->http->sendRequest($request);
        $document = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $requestId = $response->getHeaderLine('X-Request-Id');
        if ('' === $requestId && isset($document['meta']['request_id']) && \is_string($document['meta']['request_id'])) {
            $requestId = $document['meta']['request_id'];
        }

        return new ApiResponse($document, '' === $requestId ? null : $requestId);
    }
}
