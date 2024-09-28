<?php

namespace Boshnik\FastPaginate;

class Response
{
    private string $action = '';
    private int $total = 0;
    private int $limit = 10;
    private int $offset = 0;
    private int $current_page = 1;
    private string $sortby = 'id';
    private string $sortdir = 'ASC';
    private bool $paginate = false;
    private array $errors = [];
    public function __construct(public array $data = []) {}

    public function __set($name, $value)
    {
        if (property_exists($this, $name)) {
            $this->$name = $value;
        }
    }

    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        return null;
    }

    public function response(string $message = '', bool $status = false, $output = ''): array
    {
        $response = [
            'success' => $status,
            'message' => $message,
            'action' => $this->action,
            'total' => $this->total ?: count($this->data),
            'errors' => $this->errors,
        ];

        if ($this->paginate) {
            $count = count($this->data);
            $lastKey = end($this->data)[$this->sortby] ?? null;
            $response = array_merge($response, [
                'limit' => $this->limit,
                'offset' => $this->offset,
                'count' => $count,
                'show' =>  $this->limit * ($this->current_page - 1)  + $count,
                'current_page' => $this->current_page,
                'last_key' => $lastKey,
                'last_page' => $this->total
                    ? ceil($this->total / $this->limit)
                    : 0,
            ]);
        }

        $response['output'] = $output ?: $this->data;

        return $response;
    }

    public function success(string $message = '', $output = ''): array
    {
        return $this->response($message, true, $output);
    }

    public function failure(string $message = '', $output = ''): array
    {
        return $this->response($message, false, $output);
    }

}