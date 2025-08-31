<?php

namespace A8nx\Actions\File\CSV;

use A8nx\Context\Context;

class ReadAction extends \A8nx\Actions\File\ReadAction
{
    public function execute(array $params, Context &$context): array
    {
		$data = parent::execute($params, $context);

		$path = $params['path'];
		$delimiter = $params['delimiter'] ?? ',';
		$enclosure = $params['enclosure'] ?? '"';
		$escape = $params['escape'] ?? '\\';
		$hasHeader = (bool)($params['has_header'] ?? false);
		$skipEmpty = (bool)($params['skip_empty'] ?? true);
		$trimFields = (bool)($params['trim'] ?? true);
		$limit = isset($params['limit']) ? (int)$params['limit'] : null;
		$offset = (int)($params['offset'] ?? 0);

		// Normalize delimiter aliases
		if ($delimiter === '\\t' || strtolower((string)$delimiter) === 'tab') {
			$delimiter = "\t";
		}
		if (!is_string($delimiter) || strlen($delimiter) !== 1) {
			throw new \InvalidArgumentException('Delimiter must be a single character or \\t.');
		}

		$body = (string)$data['body'];
		$lines = preg_split("/(\r\n|\n|\r)/", $body);
		if ($lines === false) {
			throw new \RuntimeException('Failed to split CSV content into lines.');
		}

		$rows = [];
		$header = null;
		$lineIndex = 0;
		foreach ($lines as $line) {
			$lineIndex++;
			if ($skipEmpty && trim($line) === '') {
				continue;
			}
			if ($offset > 0) {
				$offset--;
				continue;
			}
			$row = str_getcsv($line, $delimiter, $enclosure, $escape);
			if ($row === null) {
				continue;
			}
			if ($trimFields) {
				$row = array_map(static fn($v) => is_string($v) ? trim($v) : $v, $row);
			}
			if ($hasHeader && $header === null) {
				$header = $row;
				continue;
			}
			if ($hasHeader && $header !== null) {
				// Map to associative by header
				$assoc = [];
				$max = max(count($header), count($row));
				for ($i = 0; $i < $max; $i++) {
					$key = $header[$i] ?? (string)$i;
					$assoc[$key] = $row[$i] ?? null;
				}
				$rows[] = $assoc;
			} else {
				$rows[] = $row;
			}
			if ($limit !== null && count($rows) >= $limit) {
				break;
			}
		}

		return [
			'path' => $path,
			'rows' => $rows,
			'header' => $header,
			'delimiter' => $delimiter,
			'enclosure' => $enclosure,
			'escape' => $escape,
			'num_rows' => count($rows),
			'length' => $data['length'],
			'numOfLines' => $data['numOfLines'],
			'extension' => $data['extension'],
			'success' => true,
		];
    }
}