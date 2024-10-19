<?php

namespace Boshnik\FastPaginate;

class Response
{
    private array $allowedKeys = [
        'action',
        'total',
        'limit',
        'offset',
        'sortby',
        'sortdir',
        'page',
        'last_key',
        'templates',
        'data',
        'output'
    ];

    public function __construct() {}

    public function response(string $message = '', bool $status = false, array $properties = []): array
    {
        $properties = array_intersect_key($properties, array_flip($this->allowedKeys));

        $data = $properties['data'] ?? [];
        $totalPage = count($data);
        $total = $properties['total'] ?? $totalPage;

        return array_merge([
            'success' => $status,
            'message' => $message,
            'show' => $totalPage,
            'load' => $properties['limit'] * ($properties['page'] - 1) + $totalPage,
            'last_page' => $total
                ? ceil($total / $properties['limit'])
                : 0
        ], $properties);
    }

    public function success(string $message = '', array $properties = []): array
    {
        return $this->response($message, true, $properties);
    }

    public function failure(string $message = '', array $properties = []): array
    {
        return $this->response($message, false, $properties);
    }

}