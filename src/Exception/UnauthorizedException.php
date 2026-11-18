<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Exception;

/**
 * 401 unauthorized: the credential is missing, unknown, revoked or refused.
 */
final class UnauthorizedException extends ProblemException
{
}
