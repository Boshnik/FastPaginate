<?php

/** @var modX $modx */
const MODX_API_MODE = true;
/** @noinspection PhpIncludeInspection */
require dirname(__FILE__, 4) . '/index.php';

$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

if (empty($data['action'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Action required'
    ]);
    die();
}

if (empty($data['key'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Key required'
    ]);
    die();
}

/** @var FastPaginate $fastpaginate */
if ($modx->services instanceof MODX\Revolution\Services\Container) {
    $service = $modx->services->get('fastpaginate');
    $fastpaginate = $service();
} else {
    $fastpaginate = $modx->getService('fastpaginate', 'FastPaginate', MODX_CORE_PATH . 'components/fastpaginate/model/');
}

$response = $fastpaginate->handleRequest($data['action'], $data);

echo json_encode($response ?? [],1);
@session_write_close();