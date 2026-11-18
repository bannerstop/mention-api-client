<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Http;

use Bannerstop\MentionApi\Exception as E;

/**
 * Turns a problem document into the exception class of its code.
 *
 * @internal
 */
final class ProblemMapper
{
    private const TYPE_PREFIX = 'urn:bannerstop:mention-api:problem:';

    private const CLASSES = [
        'invalid-request' => E\InvalidRequestException::class,
        'invalid-parameters' => E\InvalidParametersException::class,
        'database-not-allowed' => E\DatabaseNotAllowedException::class,
        'result-too-large' => E\ResultTooLargeException::class,
        'unauthorized' => E\UnauthorizedException::class,
        'forbidden' => E\ForbiddenException::class,
        'outside-scope' => E\OutsideScopeException::class,
        'not-in-scope' => E\NotInScopeException::class,
        'not-owner' => E\NotOwnerException::class,
        'not-found' => E\NotFoundException::class,
        'unknown-query' => E\UnknownQueryException::class,
        'unknown-routine' => E\UnknownRoutineException::class,
        'item-not-found' => E\ItemNotFoundException::class,
        'ambiguous-item' => E\AmbiguousItemException::class,
        'too-many-requests' => E\TooManyRequestsException::class,
        'circuit-open' => E\CircuitOpenException::class,
        'mention-unavailable' => E\MentionUnavailableException::class,
        'no-compiled-catalog' => E\NoCompiledCatalogException::class,
        'timeout' => E\TimeoutException::class,
        'failed' => E\ExecutionFailedException::class,
        'internal-error' => E\InternalErrorException::class,
        'http-error' => E\HttpErrorException::class,
    ];

    /**
     * @param mixed $document the decoded body of an error answer
     */
    public static function isProblem($document): bool
    {
        return \is_array($document) && isset($document['type']) && \is_string($document['type']) && 0 === strpos($document['type'], self::TYPE_PREFIX);
    }

    /**
     * @param array<string, mixed> $problem a problem document, see isProblem()
     */
    public static function exceptionFor(int $status, array $problem, ?string $requestId, ?int $retryAfter = null): E\ProblemException
    {
        $code = substr((string) $problem['type'], \strlen(self::TYPE_PREFIX));
        $class = self::CLASSES[$code] ?? E\UnknownProblemException::class;
        $errors = [];
        if (isset($problem['errors']) && \is_array($problem['errors'])) {
            foreach ($problem['errors'] as $parameter => $message) {
                $errors[(string) $parameter] = \is_scalar($message) ? (string) $message : '';
            }
        }

        return new $class(
            $status,
            $code,
            isset($problem['title']) && \is_string($problem['title']) ? $problem['title'] : 'Problem',
            isset($problem['detail']) && \is_string($problem['detail']) ? $problem['detail'] : '',
            $requestId,
            $errors,
            $retryAfter,
        );
    }
}
