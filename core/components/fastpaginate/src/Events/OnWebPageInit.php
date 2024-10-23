<?php

namespace Boshnik\FastPaginate\Events;

class OnWebPageInit extends Event
{
    public function run(): void
    {
        $this->fastpaginate->loadScripts('-b64b8b6109');
    }
}