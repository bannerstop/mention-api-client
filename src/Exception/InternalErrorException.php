<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Exception;

/**
 * 500 internal-error: an unexpected error in mention-api; report the request id.
 */
final class InternalErrorException extends ProblemException
{
}
