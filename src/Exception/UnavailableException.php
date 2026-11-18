<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Exception;

/**
 * 503: Mention or the Catalog cannot be used right now; try again later.
 */
abstract class UnavailableException extends ProblemException
{
}
