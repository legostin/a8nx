<?php

namespace A8nx\Factory;

class Step
{
    public static function parse(array $stepRawData): \A8nx\Step {
        $step = new \A8nx\Step();
        $step->setId($stepRawData['id']);
        $action = new $stepRawData['uses'];
        $step->setAction($action);
        $step->setWith($stepRawData['with'] ?? []);
        $step->setForEach($stepRawData['forEach'] ?? null);
        $step->setNeeds($stepRawData['needs'] ?? []);
        if (!empty($stepRawData['steps'])) {
            $steps = [];
            foreach ($stepRawData['steps'] as $stepRawStep) {
                $steps[] = Step::parse($stepRawStep);
            }
            $step->setSteps($steps);
        }

        $step->setCondition($stepRawData['if'] ?? null);
        return $step;
    }
}