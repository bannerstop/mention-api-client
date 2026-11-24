<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests\Fixtures;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Builds the answers mention-api gives, as documented for /v2.
 */
final class Answers
{
    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     */
    public static function json(array $body, int $status = 200, array $headers = []): ResponseInterface
    {
        return new Response($status, $headers + ['Content-Type' => 'application/json'], json_encode($body));
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public static function rows(array $rows, string $query = 'getThings', string $requestId = 'req_ok', array $headers = []): ResponseInterface
    {
        return self::json(
            ['data' => $rows, 'meta' => ['query' => $query, 'version' => 3, 'rows' => \count($rows), 'duration_ms' => 12, 'request_id' => $requestId]],
            200,
            $headers + ['X-Request-Id' => $requestId]
        );
    }

    /**
     * @param array<string, mixed>  $extra
     * @param array<string, string> $headers
     */
    public static function problem(int $status, string $code, string $title = 'Problem', string $detail = 'Something is wrong.', string $requestId = 'req_problem', array $extra = [], array $headers = []): ResponseInterface
    {
        $body = [
            'type' => 'urn:bannerstop:mention-api:problem:' . $code,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'request_id' => $requestId,
        ] + $extra;

        return new Response($status, $headers + ['Content-Type' => 'application/problem+json', 'X-Request-Id' => $requestId], json_encode($body));
    }
}
