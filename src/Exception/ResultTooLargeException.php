<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Exception;

/**
 * 422 result-too-large: the Query has more rows than its limit; narrow the Parameters.
 */
final class ResultTooLargeException extends ProblemException
{
}
