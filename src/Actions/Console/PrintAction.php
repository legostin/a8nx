<?php

namespace A8nx\Actions\Console;

use A8nx\Actions\ActionInterface;
use A8nx\Context\Context;

class PrintAction implements ActionInterface
{
    public function execute(array $params, Context &$context, array $steps = []): array {
        if (isset($params['type'])) {
            $context->getOutput()->writeln(sprintf("<%s>%s</%s>", $params['type'], $params['message'], $params['type']));
        } else {
            if (is_array($params['message'])) {
                $context->getOutput()->writeln(json_encode($params['message'], JSON_PRETTY_PRINT));
            } else {
                $context->getOutput()->writeln($params['message']);
            }
        }

        return ['success' => true];
    }
}