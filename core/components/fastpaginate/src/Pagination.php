<?php

namespace Boshnik\FastPaginate;

class Pagination
{
    public function __construct
    (
        private readonly int $currentPage,
        private readonly int $totalPages,
        private readonly string $baseUrl = '?page=')
    {}

    public function prev(): array
    {
        return [
            'page' => $this->currentPage - 1,
            'num' => $this->currentPage - 1,
            'href' => $this->currentPage > 1 ? '?page=' . ($this->currentPage - 1) : '#',
            'is_current' => $this->currentPage == 1,
            'direction' => 'prev'
        ];
    }

    public function next(): array
    {
        return [
            'page' => $this->currentPage + 1,
            'num' => $this->currentPage + 1,
            'href' => $this->currentPage < $this->totalPages ? '?page=' . ($this->currentPage + 1) : '#',
            'is_current' => $this->currentPage == $this->totalPages,
            'direction' => 'next'
        ];
    }

    public function links(): array
    {
        $currentPage = $this->currentPage;
        $totalPages = $this->totalPages;
        $baseUrl = $this->baseUrl;
        $pagination = [];

        $pagination[] = [
            'page' => 1,
            'num' => 1,
            'href' => $baseUrl . '1',
            'is_current' => $currentPage == 1
        ];

        if ($totalPages <= 9) {
            $startPage = 2;
            $endPage = $totalPages - 1;
        } else {
            if ($currentPage <= 5) {
                $startPage = 2;
                $endPage = 7;
            } elseif ($currentPage >= $totalPages - 4) {
                $startPage = $totalPages - 6;
                $endPage = $totalPages - 1;
            } else {
                $startPage = $currentPage - 2;
                $endPage = $currentPage + 2;
            }
        }

        if ($startPage > 2) {
            $pagination[] = [
                'page' => '...',
                'num' => $startPage - 1,
                'href' => $baseUrl . ($startPage - 1),
                'is_current' => false
            ];
        }

        for ($i = $startPage; $i <= $endPage; $i++) {
            $pagination[] = [
                'page' => $i,
                'num' => $i,
                'href' => $baseUrl . $i,
                'is_current' => $currentPage == $i
            ];
        }

        if ($endPage < $totalPages - 1) {
            $pagination[] = [
                'page' => '...',
                'num' => $endPage + 1,
                'href' => $baseUrl . ($endPage + 1),
                'is_current' => false
            ];
        }

        $pagination[] = [
            'page' => $totalPages,
            'num' => $totalPages,
            'href' => $baseUrl . $totalPages,
            'is_current' => $currentPage == $totalPages
        ];

        return $pagination;
    }
}