<?php

namespace A8nx\Actions\File;

use A8nx\Actions\ActionInterface;
use A8nx\Context\Context;

class WriteAction implements ActionInterface
{
    public function execute(array $params, Context &$context): array
    {
		$target = $params['path'] ?? null;

		if (!$target) {
			throw new \InvalidArgumentException('Target parameter is required');
		}

		$content = $params['content'] ?? null;
		if ($content === null) {
			throw new \InvalidArgumentException('Content parameter is required');
		}

		$append = (bool)($params['append'] ?? false);

		$dir = dirname($target);
		if (!is_dir($dir)) {
			if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
				$context->getLogger()?->error('Failed to create directory', ['dir' => $dir]);
				return [
					'path' => $target,
					'size' => null,
					'bytes_written' => 0,
					'success' => false,
					'appended' => $append,
				];
			}
		}

		$flags = LOCK_EX | ($append ? FILE_APPEND : 0);
        if ($append) {
            $content.=PHP_EOL;
        }
		$bytes = @file_put_contents($target, (string)$content, $flags);

		if ($bytes === false) {
			$context->getLogger()?->error('Failed to write file', ['path' => $target, 'append' => $append]);
			return [
				'path' => $target,
				'size' => null,
				'bytes_written' => 0,
				'success' => false,
				'appended' => $append,
			];
		}

		$realPath = realpath($target) ?: $target;
		$size = @filesize($realPath);

		$context->getLogger()?->info('File written', [
			'path' => $realPath,
			'bytes' => $bytes,
			'append' => $append,
		]);

		return [
			'path' => $realPath,
			'size' => $size === false ? null : $size,
			'bytes_written' => $bytes,
			'success' => true,
			'appended' => $append,
		];
    }
}