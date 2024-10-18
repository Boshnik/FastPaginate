<?php

namespace Boshnik\FastPaginate;

ini_set('display_errors', 1);
error_reporting(E_ALL);

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
            'next_link' => '',
            'tpl_pagination' => '',

            'action' => '',
            'wrapper' => "#{$this->namespace}",
            'outputSeparator' => "\n",
            'tpl' => '',

            'url_mode' => $this->getOption('url_mode'),
            'path_separator' => $this->getOption('path_separator'),
            'path_page_name' => $this->getOption('path_page_name'),
            'path_page' => $this->getOption('path_page'),
            'path_sort_name' => $this->getOption('path_sort_name'),
            'path_sort' => $this->getOption('path_sort'),

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

    public function getOption($name): string
    {
        return $this->modx->getOption("{$this->namespace}_{$name}", $this->properties, '', 1);
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
            $separator = $properties['path_separator'];
            $templates = [
                'page' => $properties['path_page'],
                'sort' => $properties['path_sort'],
            ];
            $path = $url_parts['path'];
            $query = $url_parts['query'] ?? '';
            $full_path = trim($path, '/') . ($query ? "?" . $query : '');

            foreach ($templates as $key => $template) {
                $regex = preg_replace_callback('/\{([a-z]+)\}/', function ($matches) use ($separator) {
                    return '(?P<' . $matches[1] . '>[^' . $separator . '/?]+)';
                }, $template);

                if (preg_match('#' . $regex . '#', $full_path, $matches)) {
                    foreach ($matches as $k => $v) {
                        if (!is_int($k)) {
                            $result[$k] = $v;
                        }
                    }
                }
            }

        } else {
            $path = $url_parts['query'] ?? '';
            $full_path = trim($path, '/');
            parse_str($full_path, $params);
            foreach ($params as $param => $value) {
                if ($param === 'sort') {
                    [$sortby, $sortdir] = explode('-', $value);
                    $properties['sortby'] = $sortby;
                    $properties['sortdir'] = $sortdir;
                } else if ($param === 'page') {
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
        $this->properties['data'] = $this->query->getData($where ?: $this->properties['where']);
        $this->properties['last_key'] = end($this->properties['data'])[$this->properties['sortby']] ?? null;

        return $this;
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

    public function loadScripts(): void
    {
        $this->modx->regClientScript($this->config['assetsUrl'] . $this->namespace . '.js');
    }

    public function initProperties(): void
    {
        $this->response = new Response();
        $this->parser = new Parser($this->modx, $this->properties);
        $this->query = new Query($this->modx, $this->properties);
    }

    public function setTotal(): void
    {
        $this->properties['total'] = $this->query->getTotal($this->properties['where'] ?: []);
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
            'path_page_name' =>  $this->properties['path_page_name'],
            'path_page' =>  $this->properties['path_page'],
            'path_sort_name' =>  $this->properties['path_sort_name'],
            'path_sort' =>  $this->properties['path_sort'],
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
            $mode = $this->properties['url_mode'] === 'get' ? '?' : '/';
            $pagination = new Pagination(
                $this->properties['page'] ?? 1,
                $total ? ceil($total / $limit) : 0,
                $mode . $this->properties['path_page']
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