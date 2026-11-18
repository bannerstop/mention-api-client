<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Exception;

/**
 * 403 not-in-scope: the Routine is not in the Scope of your Consumer.
 */
final class NotInScopeException extends ForbiddenException
{
}
