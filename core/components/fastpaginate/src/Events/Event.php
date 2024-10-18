<?php

namespace Boshnik\FastPaginate\Events;

abstract class Event
{
    /** @var \Boshnik\FastPaginate\FastPaginate $fastpaginate */
    protected \Boshnik\FastPaginate\FastPaginate $fastpaginate;

    public function __construct(protected \modX $modx, protected array $scriptProperties = [])
    {
        if ($this->modx->services instanceof \MODX\Revolution\Services\Container) {
            $this->fastpaginate = $this->modx->services->get('fastpaginate');
        } else {
            $this->fastpaginate = $this->modx->getService('fastpaginate', 'FastPaginate', MODX_CORE_PATH . 'components/fastpaginate/model/');
        }
    }

    abstract public function run();
}