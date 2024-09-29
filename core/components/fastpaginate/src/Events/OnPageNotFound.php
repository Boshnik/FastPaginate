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

        $path = $this->modx->getOption("{$this->fastpaginate->namespace}_url_path", null, '', 1);
        if (empty($path)) {
            return false;
        }
        $currentUrl = $this->fastpaginate->getCurrentUrl();
        $page = $this->fastpaginate->getPageNumber();

        $resourceId = $this->modx->getOption('site_start', null, 1, 1);

        if ($page > 1) {
            $siteUrl = rtrim(MODX_SITE_URL, '/');
            $path = str_replace('{page}', $page, $path);
            $uri = str_replace([$siteUrl, $path], '', $currentUrl);

            if (empty($uri)) {
                $aliases = explode('/', $uri);
                $alias = end($aliases);
                $this->modx->resource = $this->modx->getObject(\modResource::class, ['alias' => $alias]);
            }

            if (!$this->modx->resource) {
                $this->modx->resource = $this->modx->getObject(\modResource::class, $resourceId);
            }

            $this->fastpaginate->loadScripts();
            $this->modx->request->prepareResponse();
        }

        $this->modx->sendRedirect($this->modx->makeUrl($resourceId));
    }
}