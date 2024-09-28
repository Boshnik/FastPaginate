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

$where = $modx->getOption('where', $scriptProperties, [], true);
$paginate = $modx->getOption('paginate', $scriptProperties, false, true);
$loadMore = $modx->getOption('loadMore', $scriptProperties, false, true);
$chunk = $modx->getOption('tpl', $scriptProperties, '', true);

$fastpaginate->filters($where);

if ($paginate) {
    $fastpaginate->paginate();
}

$fastpaginate->init();

if (!empty($chunk)) {
    $response = $fastpaginate->chunk($chunk);
    if ($response['success']) {
        return $response['output'];
    }
}

return $fastpaginate->json();