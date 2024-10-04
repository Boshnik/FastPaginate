<?php

namespace Boshnik\FastPaginate\Traits;

trait Output
{
    public function getTplLoadMore($pagination): string
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

    public function getTplPagination($pagination): string
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
}