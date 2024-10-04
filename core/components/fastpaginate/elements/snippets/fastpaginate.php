<?php
/** @var modX $modx */
/** @var array $scriptProperties */

/** @var FastPaginate $fastpaginate */
if ($modx->services instanceof MODX\Revolution\Services\Container) {
    $service = $modx->services->get('fastpaginate');
    $fastpaginate = $service($scriptProperties);
} else {
    $fastpaginate = $modx->getService('fastpaginate', 'FastPaginate', MODX_CORE_PATH . 'components/fastpaginate/model/', (array)$scriptProperties);
}

$chunk = $modx->getOption('tpl', $scriptProperties, '', true);
$toPlaceholder = $modx->getOption('toPlaceholder', $scriptProperties, '', true);

$fastpaginate->init();

$output = '';
if (empty($chunk)) {
    $output = $fastpaginate->json();
} else {
    $response = $fastpaginate->chunk($chunk);
    if ($response['success'] && isset($response['output'])) {
        $output = $response['output'];
    }
}

if (!empty($toPlaceholder)) {
    $modx->setPlaceholder($toPlaceholder, $output);
    return '';
}

return $output;