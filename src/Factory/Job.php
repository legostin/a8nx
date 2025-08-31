<?php

namespace A8nx\Factory;

class Job
{
    public static function parse(array $jobRawData): \A8nx\Job
    {
        $job = new \A8nx\Job();

        $steps = [];

        foreach ($jobRawData['steps'] as $stepRawData) {
            $steps[] = Step::parse($stepRawData);
        }

        $job->setId($jobRawData['id']);
        $job->setNeeds($jobRawData['needs'] ?? []);
        $job->setSteps($steps);

        return $job;
    }
}