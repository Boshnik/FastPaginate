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

$loadMore = $modx->getOption('show.loadmore', $scriptProperties, false, true);
$paginate = $modx->getOption('show.pagination', $scriptProperties, true, true);
$chunk = $modx->getOption('tpl', $scriptProperties, '', true);
$toPlaceholder = $modx->getOption('toPlaceholder', $scriptProperties, '', true);

$fastpaginate->filters();

if ($loadMore) {
    $fastpaginate->loadMore();
}

if ($paginate) {
    $fastpaginate->paginate();
}

$fastpaginate->init();

$output = '';
if (empty($chunk)) {
    $output = $fastpaginate->json();
} else {
    $response = $fastpaginate->chunk($chunk);
    if ($response['success']) {
        $output = $response['output'];
    }
}

if (!empty($toPlaceholder)) {
    $modx->setPlaceholder($toPlaceholder, $output);
    return '';
}

return $output;