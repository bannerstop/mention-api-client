<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Http;

use Bannerstop\MentionApi\Auth\Authenticator;
use Bannerstop\MentionApi\Exception\UnexpectedResponseException;
use Bannerstop\MentionApi\MentionApiConfig;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
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
        $status = $response->getStatusCode();
        $document = json_decode((string) $response->getBody(), true);
        $requestId = $this->requestId($response, $document);

        if ($status >= 200 && $status < 300) {
            if (!\is_array($document)) {
                throw new UnexpectedResponseException('The answer is not a JSON document.', $status, $requestId);
            }

            return new ApiResponse($document, $requestId);
        }
        if (ProblemMapper::isProblem($document)) {
            throw ProblemMapper::exceptionFor($status, $document, $requestId);
        }

        throw new UnexpectedResponseException(sprintf('Unexpected answer with status %d.', $status), $status, $requestId);
    }

    /**
     * @param mixed $document
     */
    private function requestId(ResponseInterface $response, $document): ?string
    {
        $requestId = $response->getHeaderLine('X-Request-Id');
        if ('' === $requestId && \is_array($document)) {
            $candidate = $document['request_id'] ?? ($document['meta']['request_id'] ?? null);
            $requestId = \is_string($candidate) ? $candidate : '';
        }

        return '' === $requestId ? null : $requestId;
    }
}
