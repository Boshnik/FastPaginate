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

        $this->properties['where'] = array_merge(
            $this->properties['where'],
            $request['filters'] ?? [],
        );

        $this->initProperties();
        if ($action === 'filters') {
            $this->setTotal();
        }
        $total = $this->properties['total'] ?? 0;
        $limit = $this->properties['limit'] ?? 0;

        $mode = $this->properties['url_mode'] === 'url' ? '/' : '?';
        $pagination = new \Boshnik\FastPaginate\Pagination(
            $request['load_page'] ?? 1,
            $total ? ceil($total / $limit) : 0,
            "{$mode}{$this->properties['page_name']}={page}"
        );

        if (($this->properties['show.loadmore'] ?? false) && $total > $limit) {
            $this->properties['templates']['loadmore'] = $this->getTplLoadMore($pagination);
        }

        if (($this->properties['show.pagination'] ?? false) && $total > $limit) {
            $this->properties['templates']['pagination'] = $this->getTplPagination($pagination);
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