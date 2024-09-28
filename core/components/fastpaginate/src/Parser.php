<?php

namespace Boshnik\FastPaginate;

class Parser
{
    public function __construct(
        private readonly \modX $modx,
        private readonly array $properties = []
    ) {}

    public function items(string $tpl = '', array $items = []): string
    {
        if (empty($tpl)) {
            return '';
        }
        $output = [];
        foreach ($items as $item) {

            $output[] = $this->item($tpl, $item);
        }

        return implode($this->properties['outputSeparator'], $output);
    }

    public function item(string $tpl = '', array $params = []): string
    {
        return $this->modx->getChunk($tpl, $params);
    }
}