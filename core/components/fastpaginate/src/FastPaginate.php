<?php

namespace Boshnik\FastPaginate;

class FastPaginate
{
    public string $namespace = 'fastpaginate';
    public array $config = [];
    public Crypt $crypt;
    public Response $response;
    public Parser $parser;

    public string $table;
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
        ];

        $this->properties = array_merge([
            'className' => 'modResource',
            'wrapper' => "#{$this->namespace}",
            'where' => [],
            'limit' => 10,
            'offset' => 0,
            'sortby' => 'id',
            'sortdir' => 'ASC',
            'outputSeparator' => "\n",
            'tpl' => '',
            'tpl.pagination' => 'fp.pagination',
            'tpl.pagination.direction' => 'fp.pagination.direction',
            'tpl.pagination.link' => 'fp.pagination.link',
            'pls.pagination' => 'pls.pagination',
        ], array_filter($this->properties));

        $this->crypt = new Crypt($this->modx->uuid);
        $this->response = new Response();
        $this->parser = new Parser($this->modx, $this->properties);

        $this->modx->addPackage($this->namespace, $this->config['modelPath']);
        $this->modx->lexicon->load("$this->namespace:default");
    }

    public function filters(array $where = []): static
    {
        $className = $this->properties['className'];
        $this->table = $this->modx->getTableName($className) ?? $className;
        $whereSQL = $this->createWhere($where);
        $this->data = $this->getData($whereSQL);
        $this->response->data = $this->data;

        return $this;
    }

    public function paginate(): static
    {
        $whereSQL = $this->createWhere($this->properties['where']);
        if ($this->response->total === 0) {
            $this->response->total = $this->getTotal($whereSQL);
        }
        $this->response->limit = $this->properties['limit'];
        $this->response->sortby = $this->properties['sortby'];
        $this->response->sortdir = $this->properties['sortdir'];
        $this->response->paginate = true;

        return $this;
    }

    public function next(): array
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

        return $this->filters($where)->paginate()->output();
    }

    public function getData(string $where = '')
    {
        $subquery = $this->getSubQuery($where);
        $sortby = $this->properties['sortby'];
        $sortdir = $this->properties['sortdir'];

        $sql = "
            SELECT main.*
            FROM {$this->table} AS main
            JOIN (
                $subquery
            ) AS subquery ON main.id = subquery.id
            ORDER BY `$sortby` $sortdir
        ";
        $stmt = $this->modx->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getSubQuery(string $where = ''): string
    {
        $limit = $this->properties['limit'];
        $offset = $this->properties['offset'];

        return "SELECT `id` FROM {$this->table} {$where} LIMIT $limit OFFSET $offset";
    }

    public function getTotal(string $where = ''): int
    {
        $sql = "SELECT COUNT(*) AS total_records FROM {$this->table} {$where}";
        $stmt = $this->modx->query($sql);
        if ($stmt) {
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int) ($result['total_records'] ?? 0);
        }

        return 0;
    }

    public function createWhere(array $filters = []): string
    {
        if (is_string($filters)) {
            $filters = json_decode($filters, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->modx->log(1, 'Error when decoding filters.');
                return '';
            }
        }

        $where = [];
        foreach ($filters as $field => $value) {
            if (strpos($field, ':') !== false) {
                list($fieldName, $operator) = explode(':', $field, 2);
                switch ($operator) {
                    case '>':
                        $where[] = "`$fieldName` > " . $this->modx->quote($value);
                        break;
                    case '<':
                        $where[] = "`$fieldName` < " . $this->modx->quote($value);
                        break;
                    case '>=':
                        $where[] = "`$fieldName` >= " . $this->modx->quote($value);
                        break;
                    case '<=':
                        $where[] = "`$fieldName` <= " . $this->modx->quote($value);
                        break;
                    case '!=':
                        $where[] = "`$fieldName` != " . $this->modx->quote($value);
                        break;
                    case '=':
                        $where[] = "`$fieldName` = " . $this->modx->quote($value);
                        break;
                    case 'LIKE':
                        $where[] = "`$fieldName` LIKE " . $this->modx->quote("%$value%");
                        break;
                    case 'NOT LIKE':
                        $where[] = "`$fieldName` NOT LIKE " . $this->modx->quote("%$value%");
                        break;
                    case 'IN':
                        $inValues = implode(',', array_map([$modx, 'quote'], $value));
                        $where[] = "`$fieldName` IN ($inValues)";
                        break;
                    case 'NOT IN':
                        $notInValues = implode(',', array_map([$modx, 'quote'], $value));
                        $where[] = "`$fieldName` NOT IN ($notInValues)";
                        break;
                    case 'IS':
                        $where[] = "`$fieldName` IS " . ($value === null ? 'NULL' : $this->modx->quote($value));
                        break;
                    case 'IS NOT':
                        $where[] = "`$fieldName` IS NOT " . ($value === null ? 'NULL' : $this->modx->quote($value));
                        break;
                }
            } else {
                $where[] = "`$field` = " . $this->modx->quote($value);
            }
        }

        return !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
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
            $this->modx->log(1, $key['error']);
            return false;
        }
        $lastKey = end($this->data)[$this->properties['sortby']] ?? null;
        $config = json_encode([
            'url' => $this->config['actionUrl'],
            'wrapper' => $this->properties['wrapper'],
            'last_key' => $lastKey,
            'total' => $this->response->total,
            'key' => $key,
        ]);

        // Pagination
        if ($this->properties['paginate'] ?? false) {
            $this->modx->setPlaceholder(
                $this->properties['pls.pagination'],
                $this->getPagination()
            );
        }

        $this->modx->regClientScript("<script>
            document.addEventListener('DOMContentLoaded', () => {
                new FastPaginate({$config});
            });
        </script>", true);
    }

    public function getPagination(): string
    {
        $currentPage = $this->response->current_page ?? 1;
        $totalPages = $this->response->total
            ? ceil($this->response->total / $this->properties['limit'])
            : 0;

        $pagination = new Pagination($currentPage, $totalPages);

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
        $this->response->action = $action;
        $this->response->current_page = $request['load_page'] ?? 1;

        if (in_array($action, ['loadmore', 'paginate'])) {
            if (!empty($request['total'] ?? '')) {
                $this->response->total = $request['total'];
            }

            if ($this->properties['paginate'] ?? false) {
                $this->response->tplPagination = $this->getPagination();
            }
        }

        return match ($action) {
            'loadmore' => $this->loadMore($request),
            'paginate' => $this->loadPage($request),
            default => $this->response->failure('Action not allowed'),
        };
    }

    public function loadMore($request): array
    {
        if (empty($this->properties['last_key'])) {
            $this->properties['offset'] += $this->properties['limit'];
        } else {
            $this->properties['offset'] = 0;
        }

        $this->response->offset = $this->properties['offset'];

        return $this->next();
    }

    public function loadPage($request): array
    {
        $step = $request['load_page'] - $request['current_page'];
        if ($step === 1) {
            return $this->loadMore($request);
        }

        $this->properties['offset'] = ($request['load_page'] - 1) * $this->properties['limit'];

        return $this->filters($this->properties['where'])->paginate()->output();
    }

}