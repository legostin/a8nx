<?php

namespace A8nx\Context;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

class StdoutLogger extends AbstractLogger
{
    private string $minLevel;

    private const LEVELS = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT => 1,
        LogLevel::CRITICAL => 2,
        LogLevel::ERROR => 3,
        LogLevel::WARNING => 4,
        LogLevel::NOTICE => 5,
        LogLevel::INFO => 6,
        LogLevel::DEBUG => 7,
    ];

    public function __construct(string $minLevel = LogLevel::INFO)
    {
        $this->minLevel = $minLevel;
    }

    public function log($level, $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $line = sprintf('[%s] %s', strtoupper((string) $level), $this->interpolate((string) $message, $context));
        // Write to STDOUT
        fwrite(\STDOUT, $line . PHP_EOL);
    }

    private function shouldLog(string $level): bool
    {
        $min = self::LEVELS[$this->minLevel] ?? self::LEVELS[LogLevel::INFO];
        $cur = self::LEVELS[$level] ?? self::LEVELS[LogLevel::INFO];
        return $cur <= $min;
    }

    private function interpolate(string $message, array $context): string
    {
        if (str_contains($message, '{') === false) {
            return $message;
        }
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_null($val) || is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = (string) $val;
            } else {
                $replace['{' . $key . '}'] = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        return strtr($message, $replace);
    }
}


