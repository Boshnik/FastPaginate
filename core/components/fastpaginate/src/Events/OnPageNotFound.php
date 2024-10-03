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

        $properties = [
            'url_mode' => $this->fastpaginate->getOption('url_mode'),
            'path_separator' => $this->fastpaginate->getOption('path_separator'),
            'path_page' => $this->fastpaginate->getOption('path_page'),
            'path_sort' => $this->fastpaginate->getOption('path_sort'),
        ];

        $resourceId = $this->modx->getOption('site_start', null, 1, 1);

        if ($properties['url_mode'] !== 'url') {
            $this->modx->sendRedirect($this->modx->makeUrl($resourceId));
        }

        $params = $this->fastpaginate->getPageProperties();
        $parts = [];
        if (isset($params['page'])) {
            $parts[] = str_replace('{page}', $params['page'], $properties['path_page']);
        }

        if (isset($params['sortby']) && isset($params['sortdir'])) {
            $parts[] = str_replace(['{sortby}', '{sortdir}'], [$params['sortby'], $params['sortdir']], $properties['path_sort']);
        }

        $path = implode($properties['path_separator'], $parts);
        $currentUrl = $this->fastpaginate->getCurrentUrl();
        $url_parts = parse_url($currentUrl);
        $path = str_replace($path, '', $url_parts['path']);
        $path = trim($path, '/');
        $aliases = explode('/', $path);
        $alias = end($aliases);

        if (empty($alias)) {
            $this->modx->resource = $this->modx->getObject(\modResource::class, $resourceId);
        } else {
            $this->modx->resource = $this->modx->getObject(\modResource::class, ['alias' => $alias]);
        }

        if ($this->modx->resource) {
            $this->fastpaginate->loadScripts();
            $this->modx->request->prepareResponse();
        } else {
            $this->modx->sendRedirect($this->modx->makeUrl($resourceId));
        }
    }
}