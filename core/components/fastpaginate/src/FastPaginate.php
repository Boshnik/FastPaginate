<?php

namespace Boshnik\FastPaginate;

class FastPaginate
{
    public string $namespace = 'fastpaginate';
    public array $config = [];
    public Query $query;
    public Crypt $crypt;
    public Response $response;
    public Parser $parser;
    public array $data = [];

    /**
     * @param modX $modx
     * @param array $config
     */
    function __construct(public \modX $modx, public array $properties = [])
    {
        $assetsUrl = MODX_ASSETS_URL . "components/{$this->namespace}/";
        $corePath = MODX_CORE_PATH . "components/{$this->namespace}/";

        $this->config = [
            'assetsUrl' => $assetsUrl,
            'actionUrl' => $assetsUrl . 'action.php',
            'modelPath' => $corePath . 'model/',
            'siteUrl' => MODX_SITE_URL,
        ];

        $this->properties = array_merge([
            'className' => 'modResource',
            'wrapper' => "#{$this->namespace}",
            'page' => 1,
            'where' => [],
            'limit' => 10,
            'offset' => 0,
            'sortby' => 'id',
            'sortdir' => 'ASC',
            'outputSeparator' => "\n",
            'path' => $this->modx->getOption("{$this->namespace}_url_path", $this->properties, '', 1),

            'tpl' => '',

            'show.loadmore' => 0,
            'pls.loadmore' => 'pls.loadmore',
            'tpl.loadmore' => 'fp.btn.loadmore',
            'classes.loadmore' => '',

            'show.pagination' => 1,
            'pls.pagination' => 'pls.pagination',
            'tpl.pagination' => 'fp.pagination',
            'tpl.pagination.direction' => 'fp.pagination.direction',
            'tpl.pagination.link' => 'fp.pagination.link',
        ], $this->properties);

        if (is_string($this->properties['where'])) {
            $this->properties['where'] = json_decode($this->properties['where'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->modx->log(1, 'Error when decoding filters.');
                $this->properties['where'] = [];
            }
        }

        $this->query = new Query($this->modx, $this->properties);
        $this->crypt = new Crypt($this->modx->uuid);
        $this->response = new Response();
        $this->parser = new Parser($this->modx, $this->properties);

        $this->properties['page'] = $this->getPageNumber();
        if ($this->properties['page'] > 1) {
            $this->properties['offset'] = ($this->properties['page'] - 1) * $this->properties['limit'];
            $this->response->currentPage = $this->properties['page'];
        }

        $this->modx->addPackage($this->namespace, $this->config['modelPath']);
        $this->modx->lexicon->load("$this->namespace:default");
    }

    public function getPageNumber(): int
    {
        if (empty($this->properties['path'])) {
            return 1;
        }

        $currentUrl = $this->getCurrentUrl();
        $pattern = preg_quote($this->properties['path'], '/');
        $pattern = str_replace('\{page\}', '(\d+)', $pattern);

        if (preg_match('/' . $pattern . '/', $currentUrl, $matches)) {
            return $matches[1];
        }

        return 1;
    }

    public function getCurrentUrl(): string
    {
        $uri = ltrim($_SERVER['REQUEST_URI'], '/');

        return $this->config['siteUrl'] . $uri;
    }

    public function filters(array $where = []): static
    {
        $this->data = $this->query->getData($where ?: $this->properties['where']);
        $this->response->data = $this->data;

        return $this;
    }

    public function loadMore(): static
    {
        if ($this->properties['show.loadmore']) {
            $this->response->total = $this->response->total ?: $this->query->getTotal($this->properties['where'] ?: []);
            $this->response->showLoadMore = true;
        }

        return $this;
    }

    public function paginate(): static
    {
        if ($this->properties['show.pagination']) {
            $this->response->total = $this->response->total ?: $this->query->getTotal($this->properties['where'] ?: []);
            $this->response->limit = $this->properties['limit'];
            $this->response->sortby = $this->properties['sortby'];
            $this->response->sortdir = $this->properties['sortdir'];
            $this->response->showPaginate = true;
        }

        return $this;
    }

    public function nextPage(): array
    {
        $last_key = $this->properties['last_key'] ?? '';
        $where = $this->properties['where'];
        if (!empty($last_key)) {
            $sortby = $this->properties['sortby'];
            if ($this->properties['sortdir'] === 'ASC') {
                $where["$sortby:>"] = $last_key;
            } else {
                $where["$sortby:<"] = $last_key;
            }
        }

        return $this->filters($where)->loadMore()->paginate()->output();
    }

    public function loadPage(int $page = 1): array
    {
        $this->properties['offset'] = ($page - 1) * $this->properties['limit'];

        return $this->filters($this->properties['where'])->loadMore()->paginate()->output();
    }

    public function output(): array
    {
        $chunk = $this->properties['tpl'] ?? '';
        if (!empty($chunk)) {
            return $this->chunk($chunk);
        }

        return $this->response->success();
    }

    public function json(): string
    {
        return json_encode($this->response->success());
    }

    public function chunk($tpl): array
    {
        return $this->response->success('', $this->parser->items($tpl, $this->data));
    }

    public function loadScripts(): void
    {
        $this->modx->regClientScript($this->config['assetsUrl'] . $this->namespace . '.js');
    }

    public function init()
    {
        $key = $this->crypt->encrypt($this->properties);
        if (is_array($key)) {
            return false;
        }
        $lastKey = end($this->data)[$this->properties['sortby']] ?? null;
        $config = json_encode([
            'url' => $this->config['actionUrl'],
            'wrapper' => $this->properties['wrapper'],
            'page' => $this->properties['page'],
            'path' => $this->properties['path'],
            'last_key' => $lastKey,
            'total' => $this->response->total,
            'key' => $key,
        ]);

        $show = $this->response->total > $this->properties['limit'];
        if ($show) {
            $currentPage = $this->response->currentPage ?? 1;
            $totalPages = $this->response->total
                ? ceil($this->response->total / $this->properties['limit'])
                : 0;
            $pagination = new Pagination($currentPage, $totalPages, $this->properties['path']);

            // Load more
            if ($this->properties['show.loadmore']) {
                $this->modx->setPlaceholder(
                    $this->properties['pls.loadmore'],
                    $this->getLoadMore($pagination)
                );
            }

            // Pagination
            if ($show && $this->properties['show.pagination']) {
                $this->modx->setPlaceholder(
                    $this->properties['pls.pagination'],
                    $this->getPagination($pagination)
                );
            }
        }

        $this->modx->regClientScript("<script>
            document.addEventListener('DOMContentLoaded', () => {
                new FastPaginate({$config});
            });
        </script>", true);
    }

    public function getLoadMore($pagination): string
    {
        return $this->parser->item(
            $this->properties['tpl.loadmore'],
            [
                'href' => $pagination->nextLink(),
                'classes' => !empty($this->properties['classes.loadmore'])
                    ? ' ' . $this->properties['classes.loadmore']
                    : ''
            ]
        );
    }

    public function getPagination($pagination): string
    {
        $prev = $this->parser->item(
            $this->properties['tpl.pagination.direction'],
            $pagination->prev()
        );

        $next = $this->parser->item(
            $this->properties['tpl.pagination.direction'],
            $pagination->next()
        );

        $links = $this->parser->items(
            $this->properties['tpl.pagination.link'],
            $pagination->links()
        );

        return $this->parser->item(
            $this->properties['tpl.pagination'],
            [
                'prev' => $prev,
                'next' => $next,
                'links' => $links,
            ]
        );
    }

    public function handleRequest(string $action, array $request = []): array
    {
        $this->properties = array_merge(
            $this->crypt->decrypt($request['key']),
            [
                'last_key' => $request['last_key'] ?? ''
            ]
        );
        $this->query->update($this->properties);
        $this->response->action = $action;
        $this->response->currentPage = $request['load_page'] ?? 1;

        if (!empty($request['total'] ?? '')) {
            $this->response->total = $request['total'];
        }

        return match ($action) {
            'loadmore' => $this->preparePage($request),
            'paginate' => $this->preparePage($request),
            default => $this->response->failure('Action not allowed'),
        };
    }

    public function preparePage($request): array
    {
        $totalPages = $this->response->total
            ? ceil($this->response->total / $this->properties['limit'])
            : 0;
        $pagination = new Pagination(
            $this->response->currentPage,
            $totalPages,
            $this->properties['path']
        );

        if ($this->properties['show.loadmore'] ?? false) {
            $this->response->nextLink = $pagination->nextLink();
        }

        if ($this->properties['show.pagination'] ?? false) {
            $this->response->tplPagination = $this->getPagination($pagination);
        }

        $step = $request['load_page'] - $request['current_page'];
        if ($step === 1) {
            if (empty($this->properties['last_key'])) {
                $this->properties['offset'] += $this->properties['limit'];
            } else {
                $this->properties['offset'] = 0;
            }
            $this->response->offset = $this->properties['offset'];

            return $this->nextPage();
        }

        return $this->loadPage($request['load_page']);
    }

}