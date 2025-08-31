<?php

namespace A8nx\Context;

class ExpressionHandler
{
    /**
     * Applies one pipe filter to already evaluated left value.
     * Examples of expressions: "default 'empty'", "upper", "trim", "length", "json".
     */
    public static function handle(mixed $left, string $expression, Context $context)
    {
        $expression = trim($expression);
        if ($expression === '') {
            return $left;
        }

        [$fnName, $args] = self::parseFunctionCall($expression);
        return self::applyFunction($fnName, $left, $args);
    }

    private static function normalizeJsonPath(string $path): string
    {
        // Не используется в текущем варианте handle, оставлено для потенциального расширения
        if ($path === '' || $path[0] === '$' || $path[0] === '@') {
            return $path;
        }
        return '$.' . $path;
    }

    /**
     * Разбивает выражение по пайпам '|' с учётом кавычек.
     * @return array<int,string>
     */
    private static function tokenizePipes(string $input): array
    {
        $tokens = [];
        $buf = '';
        $len = strlen($input);
        $inSingle = false;
        $inDouble = false;
        for ($i = 0; $i < $len; $i++) {
            $ch = $input[$i];
            if ($ch === "'" && !$inDouble) {
                $inSingle = !$inSingle;
                $buf .= $ch;
                continue;
            }
            if ($ch === '"' && !$inSingle) {
                $inDouble = !$inDouble;
                $buf .= $ch;
                continue;
            }
            if ($ch === '|' && !$inSingle && !$inDouble) {
                $tokens[] = trim($buf);
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        if ($buf !== '') {
            $tokens[] = trim($buf);
        }
        return $tokens;
    }

    /**
     * Разбирает вызов функции вида:
     * - name arg1 arg2
     * - name(arg1, arg2)
     * Возвращает [имя, список аргументов].
     * @return array{0:string,1:array<int,mixed>}
     */
    private static function parseFunctionCall(string $expr): array
    {
        $expr = trim($expr);
        if ($expr === '') {
            return ['', []];
        }

        // Вариант с круглымми скобками: name(...)
        $openPos = strpos($expr, '(');
        $closePos = strrpos($expr, ')');
        if ($openPos !== false && $closePos === strlen($expr) - 1) {
            $name = trim(substr($expr, 0, $openPos));
            $inside = trim(substr($expr, $openPos + 1, $closePos - $openPos - 1));
            $args = self::splitArgs($inside);
            $args = array_map([self::class, 'parseLiteral'], $args);
            return [$name, $args];
        }

        // Вариант без скобок: name arg1 arg2
        $parts = self::splitByWhitespaceRespectQuotes($expr);
        $name = array_shift($parts);
        $args = array_map([self::class, 'parseLiteral'], $parts);
        return [$name, $args];
    }

    /**
     * Делит строку аргументов по запятым с учётом кавычек.
     * @return array<int,string>
     */
    private static function splitArgs(string $args): array
    {
        $out = [];
        $buf = '';
        $len = strlen($args);
        $inSingle = false;
        $inDouble = false;
        for ($i = 0; $i < $len; $i++) {
            $ch = $args[$i];
            if ($ch === "'" && !$inDouble) {
                $inSingle = !$inSingle;
                $buf .= $ch;
                continue;
            }
            if ($ch === '"' && !$inSingle) {
                $inDouble = !$inDouble;
                $buf .= $ch;
                continue;
            }
            if ($ch === ',' && !$inSingle && !$inDouble) {
                $out[] = trim($buf);
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        if (trim($buf) !== '') {
            $out[] = trim($buf);
        }
        return $out;
    }

    /**
     * Делит по пробелам с учётом кавычек.
     * @return array<int,string>
     */
    private static function splitByWhitespaceRespectQuotes(string $input): array
    {
        $out = [];
        $buf = '';
        $len = strlen($input);
        $inSingle = false;
        $inDouble = false;
        for ($i = 0; $i < $len; $i++) {
            $ch = $input[$i];
            if ($ch === "'" && !$inDouble) {
                $inSingle = !$inSingle;
                $buf .= $ch;
                continue;
            }
            if ($ch === '"' && !$inSingle) {
                $inDouble = !$inDouble;
                $buf .= $ch;
                continue;
            }
            if (preg_match('/\s/', $ch) && !$inSingle && !$inDouble) {
                if ($buf !== '') {
                    $out[] = $buf;
                    $buf = '';
                }
                continue;
            }
            $buf .= $ch;
        }
        if ($buf !== '') {
            $out[] = $buf;
        }
        return array_values(array_filter(array_map('trim', $out), fn($s) => $s !== ''));
    }

    /**
     * Преобразует строковый литерал в PHP-значение.
     * Поддерживаются: строки в одинарных/двойных кавычках, null, true/false, числа.
     * В остальных случаях — возвращает исходную строку.
     */
    private static function parseLiteral(string $token)
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }
        // Кавычки
        if ((str_starts_with($token, "'") && str_ends_with($token, "'")) ||
            (str_starts_with($token, '"') && str_ends_with($token, '"'))) {
            return substr($token, 1, -1);
        }
        // Булевы / null
        $lower = strtolower($token);
        if ($lower === 'null') { return null; }
        if ($lower === 'true') { return true; }
        if ($lower === 'false') { return false; }
        // Числа
        if (preg_match('/^-?\d+$/', $token)) { return (int) $token; }
        if (preg_match('/^-?\d*\.\d+$/', $token)) { return (float) $token; }
        return $token;
    }

    /**
     * Применяет функцию к значению.
     */
    private static function applyFunction(string $name, $value, array $args)
    {
        $name = strtolower(trim($name));
        switch ($name) {
            case 'default':
                $fallback = $args[0] ?? null;
                return ($value === null || $value === '') ? $fallback : $value;
            case 'upper':
                return strtoupper((string) $value);
            case 'lower':
                return strtolower((string) $value);
            case 'trim':
                return trim((string) $value);
            case 'json':
                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            case 'length':
                if (is_array($value) || $value instanceof \Countable) {
                    return count($value);
                }
                return mb_strlen((string) $value);
            default:
                throw new \InvalidArgumentException("Unknown function in pipeline: {$name}");
        }
    }

    /**
     * Преобразует значение в строку для подстановки.
     */
    private static function stringify($value): string
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($value === null) {
            return '';
        }
        return (string) $value;
    }
}