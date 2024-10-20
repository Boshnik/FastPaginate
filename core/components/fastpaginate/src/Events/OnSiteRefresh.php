<?php

namespace Boshnik\FastPaginate\Events;

class OnSiteRefresh extends Event
{
    public function run(): void
    {
        $this->fastpaginate->cache->clear();
    }
}