<?php

namespace A8nx\Factory;

use A8nx\Context\Context;
use A8nx\Workflow;

class SubJob
{
    public static function new(array $steps, Context $context): \A8nx\Job {
        $job = new \A8nx\Job();
        $job->setSteps($steps);
        $job->setNeeds([]);
        $job->setId($context->get('workflow.runId') .$context->get('workflow.current_step') );

        return $job;
    }

}