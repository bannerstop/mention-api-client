<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Exception;

/**
 * 422 invalid-request: the body is not what /v2 expects.
 */
final class InvalidRequestException extends ProblemException
{
}
