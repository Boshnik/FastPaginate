<?php

namespace Boshnik\FastPaginate;

class Pagination
{
    public function __construct
    (
        private readonly int $currentPage,
        private readonly int $totalPages,
        private readonly string $baseUrl = '')
    {}

    private function generateLink(int|string $page): string
    {
        return str_replace('{page}', $page, $this->baseUrl);
    }

    public function prev(): array
    {
        return [
            'page' => $this->currentPage - 1,
            'num' => $this->currentPage - 1,
            'href' => $this->currentPage > 1
                ? $this->generateLink($this->currentPage - 1)
                : '#',
            'is_current' => $this->currentPage == 1,
            'direction' => 'prev'
        ];
    }

    public function next(): array
    {
        return [
            'page' => $this->currentPage + 1,
            'num' => $this->currentPage + 1,
            'href' => $this->currentPage < $this->totalPages
                ? $this->generateLink($this->currentPage + 1)
                : '#',
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
            'href' => $this->generateLink(1),
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
                'href' => $this->generateLink($startPage - 1),
                'is_current' => false
            ];
        }

        for ($i = $startPage; $i <= $endPage; $i++) {
            $pagination[] = [
                'page' => $i,
                'num' => $i,
                'href' => $this->generateLink($i),
                'is_current' => $currentPage == $i
            ];
        }

        if ($endPage < $totalPages - 1) {
            $pagination[] = [
                'page' => '...',
                'num' => $endPage + 1,
                'href' => $this->generateLink($endPage + 1),
                'is_current' => false
            ];
        }

        $pagination[] = [
            'page' => $totalPages,
            'num' => $totalPages,
            'href' => $this->generateLink($totalPages),
            'is_current' => $currentPage == $totalPages
        ];

        return $pagination;
    }
}