<?php
/** @var MODX\Revolution\modX $modx */

require_once MODX_CORE_PATH . 'components/fastpaginate/vendor/autoload.php';

$modx->services['fastpaginate'] = $modx->services->factory(function($c) use ($modx) {
    return new Boshnik\FastPaginate\FastPaginate($modx);
});