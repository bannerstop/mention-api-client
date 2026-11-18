<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Exception;

/**
 * 403 outside-scope: the Query is not in the Scope of your Consumer.
 */
final class OutsideScopeException extends ForbiddenException
{
}
