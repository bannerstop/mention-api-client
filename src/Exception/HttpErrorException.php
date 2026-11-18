<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Exception;

/**
 * A request mention-api cannot handle (http-error).
 */
final class HttpErrorException extends ProblemException
{
}
