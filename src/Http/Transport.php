<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Http;

use Bannerstop\MentionApi\Auth\Authenticator;
use Bannerstop\MentionApi\Exception\MentionApiException;
use Bannerstop\MentionApi\Exception\TransportException;
use Bannerstop\MentionApi\Exception\UnexpectedResponseException;
use Bannerstop\MentionApi\MentionApiConfig;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Sends a call to /v2, retries it when allowed and reads the answer.
 *
 * @internal
 */
final class Transport
{
    public const MAX_RETRIES = 2;
    public const MAX_RETRY_WAIT = 10;

    private MentionApiConfig $config;
    private ClientInterface $http;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private Authenticator $authenticator;
    private ClockInterface $clock;
    private Sleeper $sleeper;

    public function __construct(
        MentionApiConfig $config,
        ClientInterface $http,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        Authenticator $authenticator,
        ClockInterface $clock,
        Sleeper $sleeper
    ) {
        $this->config = $config;
        $this->http = $http;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->authenticator = $authenticator;
        $this->clock = $clock;
        $this->sleeper = $sleeper;
    }

    /**
     * @param array<string, mixed>|null $body
     * @param bool                      $retry whether a short failure (429, 503, connection error) may be tried again
     *
     * @throws MentionApiException
     */
    public function send(string $method, string $path, ?array $body = null, bool $retry = false): ApiResponse
    {
        $tokenRefreshed = false;
        $retries = 0;
        while (true) {
            $retryAfter = null;
            $retryable = false;
            $rejected = false;
            $request = $this->request($method, $path, $body);
            try {
                $response = $this->http->sendRequest($request);
                $outcome = $this->read($response);
                $rejected = 401 === $response->getStatusCode();
                $retryable = \in_array($response->getStatusCode(), [429, 503], true);
                $retryAfter = $retryable ? $this->retryAfter($response) : null;
            } catch (NetworkExceptionInterface $e) {
                $outcome = new TransportException('The call could not be completed: ' . $e->getMessage(), $e);
                $retryable = true;
            } catch (ClientExceptionInterface $e) {
                throw new TransportException('The call could not be sent: ' . $e->getMessage(), $e);
            }
            if ($outcome instanceof ApiResponse) {
                return $outcome;
            }
            // A 401 means that nothing ran, so sending the call again with a new token is safe for Routines too.
            // This is separate from the retries below and happens once per call.
            if ($rejected && !$tokenRefreshed && $this->authenticator->reject($request->getHeaderLine('Authorization'))) {
                $tokenRefreshed = true;

                continue;
            }
            if (!$retry || !$retryable || $retries >= self::MAX_RETRIES) {
                throw $outcome;
            }
            $this->sleeper->sleep(null === $retryAfter ? (float) ($retries + 1) : (float) min($retryAfter, self::MAX_RETRY_WAIT));
            ++$retries;
        }
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function request(string $method, string $path, ?array $body): RequestInterface
    {
        $request = $this->requestFactory->createRequest($method, $this->config->getBaseUrl() . $path)
            ->withHeader('Authorization', $this->authenticator->getAuthorizationHeader())
            ->withHeader('Accept', 'application/json');
        if (null !== $body) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream(json_encode($body, JSON_THROW_ON_ERROR)));
        }

        return $request;
    }

    /**
     * @return ApiResponse|MentionApiException the result, or the exception to throw for it
     */
    private function read(ResponseInterface $response)
    {
        $status = $response->getStatusCode();
        $document = json_decode((string) $response->getBody(), true);
        $requestId = $this->requestId($response, $document);

        if ($status >= 200 && $status < 300) {
            if (!\is_array($document)) {
                return new UnexpectedResponseException('The answer is not a JSON document.', $status, $requestId);
            }

            $deprecation = $response->getHeaderLine('Deprecation');

            return new ApiResponse($document, $requestId, '' === $deprecation ? null : $deprecation);
        }
        if (ProblemMapper::isProblem($document)) {
            return ProblemMapper::exceptionFor($status, $document, $requestId, $this->retryAfter($response));
        }

        return new UnexpectedResponseException(sprintf('Unexpected answer with status %d.', $status), $status, $requestId);
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

    /**
     * Retry-After is a number of seconds or an HTTP date.
     */
    private function retryAfter(ResponseInterface $response): ?int
    {
        $value = trim($response->getHeaderLine('Retry-After'));
        if ('' === $value) {
            return null;
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }
        $date = date_create_immutable($value);
        if (false === $date) {
            return null;
        }

        return max(0, $date->getTimestamp() - $this->clock->now()->getTimestamp());
    }
}
