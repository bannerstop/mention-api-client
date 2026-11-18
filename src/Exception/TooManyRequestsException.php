<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Exception;

/**
 * 429 too-many-requests: too many calls of your Consumer run at once; try again after getRetryAfter() seconds.
 */
final class TooManyRequestsException extends ProblemException
{
}
