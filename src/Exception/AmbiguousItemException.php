<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Exception;

/**
 * 409 ambiguous-item: more than one item matches what a Routine was told to change.
 */
final class AmbiguousItemException extends ProblemException
{
}
