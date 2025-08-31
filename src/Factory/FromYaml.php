<?php

namespace A8nx\Factory;

use A8nx\Workflow;
use Symfony\Component\Yaml\Yaml;

class FromYaml
{
    public static function parse(string $path): Workflow {

        $value = Yaml::parseFile($path);
        $jobs = [];
        foreach ($value['jobs'] as $jobRawData) {
            $jobs[] = Job::parse($jobRawData);
        }

        $workflow = new Workflow($value['name'], $jobs, $value['version']);
        $workflow->fillContext($value['on']);

        return $workflow;
    }

}