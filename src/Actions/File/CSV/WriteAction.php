<?php

namespace A8nx\Actions\File\CSV;

use A8nx\Actions\ActionInterface;
use A8nx\Context\Context;

class WriteAction implements ActionInterface
{
	public function execute(array $params, Context &$context, array $steps = []): array
	{
		$path = $params['path'] ?? null;
		if (!$path) {
			throw new \InvalidArgumentException('Path parameter is required');
		}

		$rows = $params['rows'] ?? null;
		if (!is_array($rows)) {
			throw new \InvalidArgumentException('Rows parameter must be an array');
		}

		$delimiter = $params['delimiter'] ?? ',';
		$enclosure = $params['enclosure'] ?? '"';
		$escape = $params['escape'] ?? '\\';
		$hasHeader = (bool)($params['has_header'] ?? false);
		$header = $params['header'] ?? null; // optional explicit header array
		$append = (bool)($params['append'] ?? false);
		$newline = $params['newline'] ?? \PHP_EOL;

		if ($delimiter === '\\t' || strtolower((string)$delimiter) === 'tab') {
			$delimiter = "\t";
		}
		if (!is_string($delimiter) || strlen($delimiter) !== 1) {
			throw new \InvalidArgumentException('Delimiter must be a single character or \\t.');
		}

		$dir = dirname($path);
		if (!is_dir($dir)) {
			if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
				$context->getLogger()?->error('Failed to create directory', ['dir' => $dir]);
				return [
					'path' => $path,
					'size' => null,
					'bytes_written' => 0,
					'rows_written' => 0,
					'success' => false,
					'appended' => $append,
				];
			}
		}

		// Build CSV content in memory
		$stream = fopen('php://temp', 'r+');
		if ($stream === false) {
			throw new \RuntimeException('Unable to open temporary stream');
		}

		$rowsWritten = 0;

		// Determine header if requested
		if ($hasHeader) {
			if ($header === null) {
				$first = $rows[0] ?? [];
				if (is_array($first)) {
					$header = array_keys($first);
				}
			}
			if (is_array($header) && count($header) > 0) {
				fputcsv($stream, $header, $delimiter, $enclosure, $escape);
				$rowsWritten++;
			}
		}

		foreach ($rows as $row) {
			if (!is_array($row)) {
				$row = [(string)$row];
			}
			// If header exists and row is associative, align with header order
			if ($hasHeader && is_array($header)) {
				$ordered = [];
				foreach ($header as $key) {
					$ordered[] = $row[$key] ?? null;
				}
				$row = $ordered;
			}
			fputcsv($stream, $row, $delimiter, $enclosure, $escape);
			$rowsWritten++;
		}

		rewind($stream);
		$content = stream_get_contents($stream);
		fclose($stream);
		if ($content === false) {
			throw new \RuntimeException('Failed to generate CSV content');
		}

		// Normalize line endings if custom newline requested
		if ($newline !== "\n") {
			$content = str_replace(["\r\n", "\n", "\r"], "\n", $content);
			$content = str_replace("\n", (string)$newline, $content);
		}

		$flags = \LOCK_EX | ($append ? \FILE_APPEND : 0);
		$bytes = @file_put_contents($path, $content, $flags);
		if ($bytes === false) {
			$context->getLogger()?->error('Failed to write CSV file', ['path' => $path, 'append' => $append]);
			return [
				'path' => $path,
				'size' => null,
				'bytes_written' => 0,
				'rows_written' => 0,
				'success' => false,
				'appended' => $append,
			];
		}

		$realPath = realpath($path) ?: $path;
		$size = @filesize($realPath);

		$context->getLogger()?->info('CSV file written', [
			'path' => $realPath,
			'bytes' => $bytes,
			'rows' => $rowsWritten,
			'append' => $append,
		]);

		return [
			'path' => $realPath,
			'size' => $size === false ? null : $size,
			'bytes_written' => $bytes,
			'rows_written' => $rowsWritten,
			'success' => true,
			'appended' => $append,
		];
	}
}


