<?php

namespace A8nx\Context;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;

class Context
{
    private array $data = [];
    private ?InputInterface $input = null;
    private ?OutputInterface $output = null;
    private ?LoggerInterface $logger = null;

    public function __construct(string $runId)
    {
        $this->set('workflow.runId', $runId);
    }

    public function set(string $key, $value): void
    {
        $segments = $this->parseKeyToSegments($key);
        $this->setBySegments($this->data, $segments, $value);
    }

    public function setInput(InputInterface $input): void
    {
        $this->input = $input;
    }

    public function getInput(): ?InputInterface
    {
        return $this->input;
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getOutput(): ?OutputInterface
    {
        return $this->output;
    }

    public function setIo(InputInterface $input, OutputInterface $output): void
    {
        $this->input = $input;
        $this->output = $output;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Возвращает данные по JSONPath выражению.
     * Требует установленной библиотеки flow/jsonpath.
     *
     * @return mixed Возвращает массив результатов или скаляр/объект в зависимости от запроса
     */
    public function get(string $jsonPath)
    {
        if (!class_exists('\\Flow\\JSONPath\\JSONPath')) {
            throw new \RuntimeException('JSONPath library (flow/jsonpath) is not installed.');
        }

        $jsonPathObj = new \Flow\JSONPath\JSONPath($this->data);
        $result = $jsonPathObj->find($jsonPath);

        // Совместимость с разными версиями API
        if (is_object($result)) {
            if (method_exists($result, 'getData')) {
                return $result->getData()[0] ?? null;
            }
            if (method_exists($result, 'data')) {
                return $result->data()[0] ?? null;
            }
        }

        return $result;
    }

    /**
     * Convert key string to path segments.
     * - If key starts with '/', treat it as JSON Pointer (RFC 6901)
     * - Otherwise, treat it as simple dot notation: a.b.0.c
     *
     * @return array<int|string>
     */
    private function parseKeyToSegments(string $key): array
    {
        $key = trim($key);
        if ($key === '') {
            throw new \InvalidArgumentException('Path must not be empty.');
        }

        if ($key[0] === '/') {
            return $this->parseJsonPointer($key);
        }

        return $this->parseDotPath($key);
    }

    /**
     * Parse JSON Pointer (RFC 6901) into segments.
     * @return array<int|string>
     */
    private function parseJsonPointer(string $pointer): array
    {
        if ($pointer === '/') {
            // Single root slash refers to empty-key child
            return [''];
        }
        if ($pointer === '') {
            throw new \InvalidArgumentException('Path must not be empty.');
        }
        if ($pointer[0] !== '/') {
            throw new \InvalidArgumentException('JSON Pointer must start with "/".');
        }

        $rawSegments = explode('/', substr($pointer, 1));
        $segments = [];
        foreach ($rawSegments as $seg) {
            // Unescape ~1 -> '/', ~0 -> '~'
            $seg = str_replace(['~1', '~0'], ['/', '~'], $seg);
            if ($seg !== '' && preg_match('/^-?\d+$/', $seg) === 1) {
                $segments[] = (int) $seg;
            } else {
                $segments[] = $seg;
            }
        }
        return $segments;
    }

    /**
     * Parse simple dot notation: a.b.0.c
     * @return array<int|string>
     */
    private function parseDotPath(string $path): array
    {
        $parts = explode('.', $path);
        $segments = [];
        foreach ($parts as $part) {
            if ($part === '') {
                throw new \InvalidArgumentException('Empty identifier in path.');
            }
            if (preg_match('/^-?\d+$/', $part) === 1) {
                $segments[] = (int) $part;
            } else {
                $segments[] = $part;
            }
        }
        return $segments;
    }

    /**
     * @param array<int|string,mixed> $target
     * @param array<int|string> $segments
     * @param mixed $value
     */
    private function setBySegments(array &$target, array $segments, $value): void
    {
        $current =& $target;
        $last = count($segments) - 1;
        foreach ($segments as $i => $segment) {
            $isLast = ($i === $last);

            if (is_int($segment)) {
                if (!is_array($current)) {
                    $current = [];
                }
                if ($isLast) {
                    $current[$segment] = $value;
                    return;
                }
                if (!array_key_exists($segment, $current) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current =& $current[$segment];
                continue;
            }

            // string key
            if (!is_array($current)) {
                $current = [];
            }
            if ($isLast) {
                $current[$segment] = $value;
                return;
            }
            if (!array_key_exists($segment, $current) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current =& $current[$segment];
        }
    }

    public function setVerbose(string $verbose)
    {
        $this->set('verbose', $verbose);
    }
}