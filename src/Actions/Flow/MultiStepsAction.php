<?php

namespace A8nx\Actions\Flow;

use A8nx\Actions\ActionInterface;
use A8nx\Context\Context;
use A8nx\Factory\SubJob;

class MultiStepsAction implements ActionInterface
{

    public function execute(array $params, Context &$context, array $steps = []): array
    {
        $job = SubJob::new($steps, $context);

        return ['success' => $job->run($context)];
    }
}