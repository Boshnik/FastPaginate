<?php

namespace Boshnik\FastPaginate;

use Boshnik\FastPaginate\Traits\Output;
use Boshnik\FastPaginate\Traits\HandleRequest;

class FastPaginate
{
    use Output;
    use HandleRequest;

    public string $namespace = 'fastpaginate';
    public array $keySetPaginataion = ['id', 'menuindex'];
    public array $config = [];
    public array $properties = [];
    public Query $query;
    public Cache $cache;
    public Crypt $crypt;
    public Response $response;
    public Parser $parser;

    function __construct(public \modX $modx, array $properties = [])
    {
        $assetsUrl = MODX_ASSETS_URL . "components/{$this->namespace}/";
        $corePath = MODX_CORE_PATH . "components/{$this->namespace}/";

        $this->config = array_merge([
            'assetsUrl' => $assetsUrl,
            'actionUrl' => $assetsUrl . 'action.php',
            'modelPath' => $corePath . 'model/',
            'siteUrl' => MODX_SITE_URL,
        ], $properties);

        $this->cache = new Cache($this->modx, [
            \xPDO::OPT_CACHE_KEY => $this->namespace,
        ], $this->getOption('cache_time', 3600));
        $this->crypt = new Crypt($this->modx->uuid);

        $this->modx->addPackage($this->namespace, $this->config['modelPath']);
        $this->modx->lexicon->load("{$this->namespace}:default");
    }

    public function defaultProperties(): array
    {
        return [
            'className' => 'modResource',
            'fields' => '*',
            'where' => [],
            'total' => 0,
            'limit' => 10,
            'offset' => 0,
            'sortby' => 'id',
            'sortdir' => 'asc',
            'page' => 1,
            'last_key' => 0,
            'templates' => [
                'loadmore' => '',
                'pagination' => '',
            ],
            'action' => '',
            'wrapper' => "#{$this->namespace}",
            'outputSeparator' => "\n",
            'tpl' => '',

            'url_mode' => $this->getOption('url_mode', 'get'),
            'path_separator' => $this->getOption('path_separator', ';'),
            'page_name' => $this->getOption('page_name', 'page'),
            'sort_name' => $this->getOption('sort_name', 'sort'),
            'cache' => $this->getOption('cache', false),
            'cacheTime' => $this->getOption('cache_time', 3600),

            'show.loadmore' => 0,
            'pls.loadmore' => 'pls.loadmore',
            'tpl.loadmore' => 'fp.btn.loadmore',
            'classes.loadmore' => '',

            'show.pagination' => 1,
            'pls.pagination' => 'pls.pagination',
            'tpl.pagination' => 'fp.pagination',
            'tpl.pagination.direction' => 'fp.pagination.direction',
            'tpl.pagination.link' => 'fp.pagination.link',

            'pls.total' => 'pls.total',
        ];
    }

    public function getOption($name, $default = ''): string
    {
        return $this->modx->getOption("{$this->namespace}_{$name}", $this->properties, $default, 1);
    }

    public function prepareScriptProperties(array $scriptProperties = []): array
    {
        if (empty($scriptProperties['where'] ?? '')) {
            $scriptProperties['where'] = [];
        }

        if (is_string($scriptProperties['where'])) {
            $scriptProperties['where'] = json_decode($scriptProperties['where'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->modx->log(1, 'Error when decoding filters.');
                $scriptProperties['where'] = [];
            }
        }

        return $scriptProperties;
    }

    public function getPageProperties(array $properties = []): array
    {
        $currentUrl = $this->getCurrentUrl();
        $url_parts = parse_url($currentUrl);

        if ($properties['url_mode'] === 'url') {
            $path = $url_parts['path'];
            $query = $url_parts['query'] ?? '';
            $full_path = trim($path, '/') . ($query ? "?" . $query : '');
            $full_path = str_replace($properties['path_separator'], '&', $full_path);
        } else {
            $path = $url_parts['query'] ?? '';
            $full_path = trim($path, '/');
        }
        $full_path = str_replace('?', '', $full_path);
        parse_str($full_path, $params);

        foreach ($params as $param => $value) {
            if ($param === $properties['sort_name']) {
                [$sortby, $sortdir] = explode('-', $value);
                $properties['sortby'] = $sortby;
                $properties['sortdir'] = $sortdir;
            } else if ($param === $properties['page_name']) {
                $properties['page'] = $value;
            } else {
                if (strpos($value, ',') !== false) {
                    $value = explode(',', $value);
                }

                if (!is_array($value) && strpos($value, '-') !== false) {
                    $price = explode('-', $value);
                    if (count($price) === 2 && is_numeric($price[0])) {
                        $value = [
                            'min' => $price[0],
                            'max' => $price[1]
                        ];
                    }
                }

                $properties['where'][$param] = $value;
                $properties['filters'][$param] = $value;
            }
        }

        return $properties;
    }

    public function getCurrentUrl(): string
    {
        $uri = ltrim($_SERVER['REQUEST_URI'], '/');

        return $this->config['siteUrl'] . $uri;
    }

    public function filters(array $where = []): static
    {
        if ($this->properties['cache']) {
            $cacheParams = [
                ...$where ?: $this->properties['where'],
                ...array_intersect_key($this->properties, array_flip([
                    'className', 'fields', 'limit', 'offset', 'sortby', 'sortdir', 'last_key'
                ]))
            ];
            $cacheKey = $this->getCacheKey('getData', $cacheParams);
            $cachedData = $this->cache->get($cacheKey);
            if ($cachedData) {
                $this->properties['data'] = $cachedData;
            } else {
                $this->properties['data'] = $this->query->getData($where ?: $this->properties['where']);
                $this->cache->set($cacheKey, $this->properties['data'], $this->properties['cacheTime']);
            }
        } else {
            $this->properties['data'] = $this->query->getData($where ?: $this->properties['where']);
        }

        $this->properties['last_key'] = end($this->properties['data'])[$this->properties['sortby']] ?? null;

        return $this;
    }

    public function setTotal(array $where = []): void
    {
        if ($this->properties['cache']) {
            $cacheKey = $this->getCacheKey('getTotal', $where ?: $this->properties['where']);
            $cachedTotal = $this->cache->get($cacheKey);
            if ($cachedTotal) {
                $this->properties['total'] = $cachedTotal;
            } else {
                $this->properties['total'] = $this->query->getTotal($where ?: $this->properties['where']);
                $this->cache->set($cacheKey, $this->properties['total'], $this->properties['cacheTime']);
            }
        } else {
            $this->properties['total'] = $this->query->getTotal($where ?: $this->properties['where']);
        }
    }

    public function getCacheKey(string $name, array $params = []): string
    {
        return "{$this->namespace}/{$name}/" . md5(json_encode($params));
    }

    public function nextPage(): array
    {
        $last_key = $this->properties['last_key'] ?? '';
        $where = $this->properties['where'];
        if (!empty($last_key)) {
            $sortby = $this->properties['sortby'];
            $sortdir = strtolower($this->properties['sortdir']);
            if ($sortdir === 'asc') {
                $where["$sortby:>"] = $last_key;
            } else {
                $where["$sortby:<"] = $last_key;
            }
        }

        return $this->process($where);
    }

    public function loadPage(int $page = 1): array
    {
        $this->properties['offset'] = ($page - 1) * $this->properties['limit'];

        return $this->process($this->properties['where']);
    }

    public function process(array $where = []): array
    {
        return $this->filters($where)->output();
    }

    public function output(): array
    {
        $chunk = $this->properties['tpl'] ?? '';
        if (!empty($chunk)) {
            return $this->chunk($chunk);
        }

        return $this->response->success('', $this->properties);
    }

    public function json(): string
    {
        return json_encode($this->response->success('', $this->properties));
    }

    public function chunk($tpl): array
    {
        $this->properties['output'] = $this->parser->items($tpl, $this->properties['data'] ?? []);
        return $this->response->success('', $this->properties);
    }

    public function loadScripts(string $hash = ''): void
    {
        $this->modx->regClientScript("{$this->config['assetsUrl']}{$this->namespace}{$hash}.js");
    }

    public function initProperties(): void
    {
        $this->response = new Response();
        $this->parser = new Parser($this->modx, $this->properties);
        $this->query = new Query($this->modx, $this->properties);
    }

    public function init(array $scriptProperties = [])
    {
        $scriptProperties = $this->prepareScriptProperties($scriptProperties);
        $properties = [...$this->defaultProperties(), ...$scriptProperties];
        $this->properties = $this->getPageProperties($properties);

        if ($this->properties['page'] > 1) {
            $this->properties['offset'] = ($this->properties['page'] - 1) * $this->properties['limit'];
        }

        $this->initProperties();
        $this->setTotal();
        $this->filters();

        $key = $this->crypt->encrypt($scriptProperties);
        if (is_array($key)) {
            return false;
        }

        $config = json_encode([
            'actionUrl' => $this->config['actionUrl'],
            'wrapper' => $this->properties['wrapper'],
            'page' => (int)$this->properties['page'],
            'url_mode' =>  $this->properties['url_mode'],
            'path_separator' =>  $this->properties['path_separator'],
            'page_name' =>  $this->properties['page_name'],
            'sort_name' =>  $this->properties['sort_name'],
            'sortby' => $this->properties['sortby'],
            'sortdir' => $this->properties['sortdir'],
            'last_key' => (int)$this->properties['last_key'],
            'total' => (int)$this->properties['total'],
            'show' => $this->properties['total'] > $this->properties['limit']
                ? $this->properties['limit']
                : $this->properties['total'],
            'filters' => $this->properties['filters'] ?? [],
            'key' => $key,
        ]);

        $this->setPlaceholders();

        $this->modx->regClientScript("<script>
            document.addEventListener('DOMContentLoaded', () => {
                new FastPaginate({$config});
            });
        </script>", true);
    }

    public function setPlaceholders(): void
    {
        $total = $this->properties['total'] ?? 0;
        $limit = $this->properties['limit'] ?? 0;

        $showLoadMore = $this->properties['show.loadmore'] ?? false;
        $showPagination = $this->properties['show.pagination'] ?? false;

        $this->parser->setPlaceholder('total', $total);

        if (($showLoadMore || $showPagination) && $total > $limit) {
            $mode = $this->properties['url_mode'] === 'url' ? '/' : '?';
            $pagination = new Pagination(
                $this->properties['page'] ?? 1,
                $total ? ceil($total / $limit) : 0,
                "{$mode}{$this->properties['page_name']}={page}"
            );

            if ($showLoadMore) {
                $this->parser->setPlaceholder('loadmore', $this->getTplLoadMore($pagination));
            }

            if ($showPagination) {
                $this->parser->setPlaceholder('pagination', $this->getTplPagination($pagination));
            }
        }
    }

}