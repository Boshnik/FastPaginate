<?php

namespace Boshnik\FastPaginate\Traits;

trait HandleRequest
{
    public function handleRequest(string $action, array $request = []): array
    {
        $this->prepareHandleRequest($action, $request);

        return match ($action) {
            'loadmore', 'paginate' => $this->prepareLoadPage($request),
            'sort', 'filters' => $this->process(),
            default => $this->response->failure('Action not allowed', $request),
        };
    }

    public function prepareHandleRequest(string $action, array $request = []): void
    {
        $this->properties = array_merge(
            $this->defaultProperties(),
            $this->crypt->decrypt($request['key'] ?? ''),
            [
                'action' => $action,
                'page' => $request['load_page'] ?? 1,
                'last_key' => $request['last_key'] ?? '',
                'total' => $request['total'] ?? '',
                'sortby' => $request['sortby'] ?? $this->properties['sortby'],
                'sortdir' => $request['sortdir'] ?? $this->properties['sortdir'],
            ]
        );

        if (!empty($request['filters'] ?? null)) {
            $filters = array_filter($request['filters']);
            foreach ($filters as $name => $value) {
                if (is_array($value)) {
                    $this->properties['where'][$name . ':IN'] = $value;
                } else {
                    $this->properties['where'][$name] = $value;
                }
            }
        }

        $this->query = new \Boshnik\FastPaginate\Query($this->modx, $this->properties);

        if ($action === 'filters') {
            $this->properties['total'] = $this->query->getTotal($this->properties['where'] ?: []);
        }

        $total = $this->properties['total'] ?? 0;
        $limit = $this->properties['limit'] ?? 0;

        $mode = $this->properties['path_page'] === 'get' ? '?' : '/';
        $pagination = new \Boshnik\FastPaginate\Pagination(
            $request['load_page'] ?? 1,
            $total ? ceil($total / $limit) : 0,
            $mode . $this->properties['path_page']
        );

        if ($this->properties['show.loadmore'] ?? false) {
            $this->properties['next_link'] = $pagination->nextLink();
        }

        if (($this->properties['show.pagination'] ?? false) && $total > $limit) {
            $this->properties['tpl_pagination'] = $this->getTplPagination($pagination);
        }
    }

    public function prepareLoadPage($request): array
    {
        $step = ($request['load_page'] ?? 1) - ($request['current_page'] ?? 1);
        if (in_array($this->properties['sortby'], $this->keySetPaginataion) && $step === 1) {
            if (empty($this->properties['last_key'])) {
                $this->properties['offset'] += $this->properties['limit'];
            } else {
                $this->properties['offset'] = 0;
            }

            return $this->nextPage();
        }

        return $this->loadPage($request['load_page'] ?? 1);
    }

}