<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Exception;

/**
 * 403 not-owner: the Query belongs to another Consumer.
 */
final class NotOwnerException extends ForbiddenException
{
}
