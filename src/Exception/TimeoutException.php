<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Exception;

/**
 * 504 timeout: the Query or Routine ran longer than its time limit.
 */
final class TimeoutException extends ProblemException
{
}
