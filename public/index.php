<?php declare(strict_types=1);

require __DIR__ . '/../prepend.inc.php';

use Cog\BaseApplication;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel;

/** @var HttpKernel\HttpKernelInterface $kernel */
$kernel = BaseApplication::$container->get('kernel');

$response = $kernel->handle(Request::createFromGlobals());
$response->send();
