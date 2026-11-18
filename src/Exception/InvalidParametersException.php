<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Exception;

/**
 * 422 invalid-parameters: a Parameter is missing, unknown or of the wrong type; see getErrors().
 */
final class InvalidParametersException extends ProblemException
{
}
