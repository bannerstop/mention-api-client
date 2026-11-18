<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Exception;

/**
 * 404 unknown-query: there is no such Query, or it was retired.
 */
final class UnknownQueryException extends NotFoundException
{
}
