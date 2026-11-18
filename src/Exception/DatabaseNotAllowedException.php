<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Exception;

/**
 * 422 database-not-allowed: the Mention database is unknown or not allowed for this Query or Routine.
 */
final class DatabaseNotAllowedException extends ProblemException
{
}
