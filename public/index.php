<?php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;

if (isset($_SERVER['XHPROF_ENABLE']) && $_SERVER['XHPROF_ENABLE'] == 1) {
    xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);
}

require dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);

    Debug::enable();
}

if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? false) {
    Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
}

if ($trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? false) {
    Request::setTrustedHosts([$trustedHosts]);
}

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);

if (isset($_SERVER['XHPROF_ENABLE']) && $_SERVER['XHPROF_ENABLE'] == 1) {
    $xhprofData = xhprof_disable();
    $debugId = uniqid();
    $dir = ini_get('xhprof.output_dir');
    $fp = fopen($dir.'/'.$debugId.'.xhprof.xhprof', 'w');
    fwrite($fp, serialize($xhprofData));
    fclose($fp);
    $response->headers->set('X-Prof-Url', 'http://localhost:8881/index.php?run='.$debugId.'&source=xhprof');
}

$response->send();
$kernel->terminate($request, $response);
