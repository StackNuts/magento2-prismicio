<?php declare(strict_types=1);

namespace Elgentos\PrismicIO\Exception;

/**
 * Thrown when the repository is known to be unreachable, so we skip calling it again
 */
class ApiUnavailableException extends GeneralException
{
}
