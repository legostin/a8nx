<?php

namespace A8nx;

use A8nx\Context\Context;

class Job
{
    private string $id;

    /**
     * @var \A8nx\Step[]
     */
    private array $steps = [];

    /**
     * @var string[]
     */
    private array $needs = [];

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void {
        $this->id = $id;
    }

    /**
     * @return \A8nx\Step[]
     */
    public function getSteps(): array {
        return $this->steps;
    }

    public function setSteps(array $steps): void {
        $this->steps = $steps;
    }
    public function getNeeds(): array {
        return $this->needs;
    }

    public function setNeeds(array $needs): void {
        $this->needs = $needs;
    }

    public function run(Context &$context): int {
		$orderedSteps = $this->topologicalOrderSteps($this->steps);
        foreach ($orderedSteps as $step) {
            $step->run($context);
        }
        return 0;
    }

	/**
	 * Возвращает шаги в топологическом порядке согласно needs.
	 * @return Step[]
	 */
	public function getStepsInOrder(): array
	{
		return $this->topologicalOrderSteps($this->steps);
	}

    /**
     * @param Step[] $steps
     * @return Step[]
     */
    private function topologicalOrderSteps(array $steps): array
    {
        $idToStep = [];
        foreach ($steps as $step) {
            $idToStep[$step->getId()] = $step;
        }

        $graph = [];
        $inDegree = [];
        foreach ($steps as $step) {
            $sid = $step->getId();
            $deps = $step->getNeeds();
            $graph[$sid] = $graph[$sid] ?? [];
            $inDegree[$sid] = $inDegree[$sid] ?? 0;
            foreach ($deps as $depId) {
                if (!isset($idToStep[$depId])) {
                    throw new \RuntimeException("Step '{$sid}' depends on missing step '{$depId}'.");
                }
                $graph[$depId] = $graph[$depId] ?? [];
                $graph[$depId][] = $sid; // dep -> step
                $inDegree[$sid] = ($inDegree[$sid] ?? 0) + 1;
                $inDegree[$depId] = $inDegree[$depId] ?? 0;
            }
        }

        $queue = [];
        foreach ($inDegree as $node => $deg) {
            if ($deg === 0) {
                $queue[] = $node;
            }
        }

        $ordered = [];
        while ($queue) {
            $node = array_shift($queue);
            $ordered[] = $idToStep[$node];
            foreach ($graph[$node] ?? [] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        if (count($ordered) !== count($steps)) {
            throw new \RuntimeException('Cyclic dependency detected among steps.');
        }

        return $ordered;
    }
}