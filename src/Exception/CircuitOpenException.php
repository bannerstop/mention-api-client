<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Exception;

/**
 * 503 circuit-open: calls to Mention are paused after repeated failures.
 */
final class CircuitOpenException extends UnavailableException
{
}
