<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests\Fixtures;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A scripted PSR-18 client: answers are used in the order they were queued, a queued Throwable is thrown.
 * The last queued answer is repeated when the queue runs dry.
 */
final class FakeHttpClient implements ClientInterface
{
    /** @var list<ResponseInterface|\Throwable> */
    private array $queue = [];

    /** @var list<RequestInterface> */
    public array $requests = [];

    /**
     * @param ResponseInterface|\Throwable $answer
     */
    public function enqueue($answer): self
    {
        $this->queue[] = $answer;

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        if ([] === $this->queue) {
            throw new \LogicException('No answer queued for ' . $request->getMethod() . ' ' . $request->getUri());
        }
        $answer = \count($this->queue) > 1 ? array_shift($this->queue) : $this->queue[0];
        if ($answer instanceof \Throwable) {
            throw $answer;
        }

        return $answer;
    }

    public function lastRequest(): RequestInterface
    {
        return $this->requests[\count($this->requests) - 1];
    }

    /**
     * @return array<string, mixed>
     */
    public function lastJsonBody(): array
    {
        return json_decode((string) $this->lastRequest()->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
