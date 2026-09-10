<?php declare(strict_types=1);

namespace Cog\Test\fixtures\Kernel;

use RuntimeException;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(418, ['X-Tea' => 'earl grey'])]
class TeapotException extends RuntimeException {
}
