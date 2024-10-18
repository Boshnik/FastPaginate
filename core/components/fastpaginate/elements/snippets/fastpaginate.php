<?php
/** @var modX $modx */
/** @var array $scriptProperties */

/** @var FastPaginate $fastpaginate */
if ($modx->services instanceof MODX\Revolution\Services\Container) {
    $fastpaginate = $modx->services->get('fastpaginate');
} else {
    $fastpaginate = $modx->getService('fastpaginate', 'FastPaginate', MODX_CORE_PATH . 'components/fastpaginate/model/');
}
if (!$fastpaginate) {
    return 'Could not load FastPaginate class!';
}

$chunk = $modx->getOption('tpl', $scriptProperties, '', true);
$toPlaceholder = $modx->getOption('toPlaceholder', $scriptProperties, '', true);

$fastpaginate->init($scriptProperties);

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