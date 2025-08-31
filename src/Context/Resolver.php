<?php

namespace A8nx\Context;

class Resolver
{
    public static function resolve($key, Context $context): mixed
    {
        if (is_array($key)) {
            $result = [];
            foreach ($key as $k => $v) {
                $resolvedKey = self::resolve($k, $context);
                $resolvedValue = self::resolve($v, $context);
                $result[$resolvedKey] = $resolvedValue;
            }

            return $result;
        }

        if (!is_string($key)) {
            return $key;
        }

        // Если строка состоит только из одной переменной с пайпами, возвращаем значение как есть (с сохранением типа)
        if (preg_match('/^\s*\$\{([^}]+)\}\s*$/', $key, $singleMatch)) {
            $expr = $singleMatch[1];
            $pipes = explode('|', $expr);
            $varPath = trim((string) array_shift($pipes));
            $value = $context->get($varPath);

            foreach ($pipes as $pipe) {
                $value = ExpressionHandler::handle($value, trim($pipe), $context);
            }

            return $value;
        }

        if (preg_match_all('/\$\{([^}]+)\}/', $key, $matches)) {
            foreach ($matches[1] as $i => $path) {
                $pipes = explode("|", $path);
                $varPath = trim(array_shift($pipes));
                $value = $context->get($varPath);

                if ($pipes) {
                    foreach ($pipes as $pipe) {
                        $value = ExpressionHandler::handle($value, trim($pipe), $context);
                    }
                }

                if (is_string($value) || is_numeric($value)) {
                    $key = str_replace($matches[0][$i], $value, $key);
                }

                if (is_array($value)) {
                    $key = str_replace(
                        $matches[0][$i],
                        sprintf(
                            "%s%s%s",
                            PHP_EOL,
                            json_encode($value, JSON_PRETTY_PRINT),
                            PHP_EOL ),
                        $key
                    );

                }

            }
        }

        return $key;
    }
}