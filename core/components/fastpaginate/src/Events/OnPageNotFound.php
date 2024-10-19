<?php

namespace Boshnik\FastPaginate\Events;

class OnPageNotFound extends Event
{
    public function run()
    {
        if ($this->modx->context->key === 'mgr') {
            return false;
        }

        if (!$this->modx->getOption('site_status') && !$this->modx->user->id) {
            return false;
        }

        $properties = $this->fastpaginate->defaultProperties();
        if ($properties['url_mode'] !== 'url') {
            return false;
        }

        $currentUrl = $this->fastpaginate->getCurrentUrl();
        $url_parts = parse_url($currentUrl);
        $aliases = explode('/', $url_parts['path']);
        $alias = $aliases[count($aliases)-3] ?? null;

        if (empty($alias)) {
            $resourceId = $this->modx->getOption('site_start', null, 1, 1);
            $this->modx->resource = $this->modx->getObject(\modResource::class, $resourceId);
        } else {
            $this->modx->resource = $this->modx->getObject(\modResource::class, ['alias' => $alias]);
        }

        if ($this->modx->resource) {
            $this->fastpaginate->loadScripts();
            $this->modx->request->prepareResponse();
        } else {
            $resourceId = $this->modx->getOption('site_start', null, 1, 1);
            $this->modx->sendRedirect($this->modx->makeUrl($resourceId));
        }
    }
}