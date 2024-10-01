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

    public function setPlaceholder($name, $value): void
    {
        if (!empty($this->properties["pls.$name"] ?? '')) {
            $this->modx->setPlaceholder(
                $this->properties["pls.$name"],
                $value
            );
        }
    }
}