<?php

namespace A8nx\Actions\File;

use A8nx\Actions\ActionInterface;
use A8nx\Context\Context;

class ReadAction implements ActionInterface
{

    public function execute(array $params, Context &$context, array $steps = []): array
    {
        if (!isset($params['path'])) {
            throw new \InvalidArgumentException('Path parameter is required');
        }

        $path = $params['path'];
        if (!file_exists($path)) {
            throw new \RuntimeException("File not found: $path");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Unable to read file: $path");
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $numOfLines = substr_count($content, "\n") + 1;
        return [
            'body' => $content,
            'length' => strlen($content),
            'numOfLines' => $numOfLines,
            'extension' => $extension
        ];
    }
}