<?php

namespace A8nx;

use A8nx\Context\Context;
use A8nx\Context\StdoutLogger;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\OutputInterface;

class Workflow
{
    private string $name;

    private string $runId;
    /**
     * @var \A8nx\Job[]
     */
    private array $jobs;

    private string $version;

    private string $verbose = '';

    private string $trigger = 'workflow_dispatch';

    /**
     * @var \A8nx\Context\Context
     */
    private Context $context;

    public function __construct(string $name, array $jobs, string $version) {
        $this->name = $name;
        $this->jobs = $jobs;
        $this->version = $version;
        $this->context = new Context();
        $this->runId = uniqid();
    }

    public function fillContext(array $rawData) {
        $data = [];
        if ($rawData['workflow_dispatch']) {
            $data = $rawData['workflow_dispatch']['inputs'];
        }

        $this->context->set('inputs', $data);
    }

    public function run(): int {

        $context = $this->getContext();

        // Настраиваем логгер из verbose
        $rawVerbose = $this->verbose;

        $level = match ((int) $rawVerbose) {
            OutputInterface::OUTPUT_NORMAL => LogLevel::ERROR,
            OutputInterface::VERBOSITY_VERBOSE => LogLevel::INFO,
            OutputInterface::VERBOSITY_DEBUG => LogLevel::DEBUG,
            OutputInterface::VERBOSITY_VERY_VERBOSE => LogLevel::WARNING,
            default => LogLevel::ERROR,
        };

        $context->setLogger(new StdoutLogger($level));

        // Выполняем джобы по топологическому порядку согласно needs
        $orderedJobs = $this->topologicalOrderJobs($this->jobs);

        // Если verbose явно передан — вывести план выполнения
        $logger = $context->getLogger();

        if ($logger && $this->verbose !== '') {
            $plan = [];
            foreach ($orderedJobs as $job) {
                $stepIds = array_map(static fn($s) => $s->getId(), $job->getStepsInOrder());
                $plan[] = [
                    'job' => $job->getId(),
                    'needs' => $job->getNeeds(),
                    'steps_order' => $stepIds,
                ];
            }
            $logger->info('Execution plan', [
                'workflow' => $this->getName(),
                'run_id' => $this->getRunId(),
                'jobs' => $plan,
            ]);
        }
        foreach ($orderedJobs as $job) {
            $job->run($context);
        }

        return 0;
    }

    /**
     * Возвращает массив Job в топологическом порядке. Бросает исключение при цикле.
     * @param Job[] $jobs
     * @return Job[]
     */
    private function topologicalOrderJobs(array $jobs): array
    {
        // Индексы
        $idToJob = [];
        foreach ($jobs as $job) {
            $idToJob[$job->getId()] = $job;
        }

        // Граф зависимостей: jobId => [depId, ...]
        $graph = [];
        $inDegree = [];
        foreach ($jobs as $job) {
            $jobId = $job->getId();
            $deps = $job->getNeeds();
            $graph[$jobId] = [];
            $inDegree[$jobId] = $inDegree[$jobId] ?? 0;
            foreach ($deps as $depId) {
                if (!isset($idToJob[$depId])) {
                    throw new \RuntimeException("Job '{$jobId}' depends on missing job '{$depId}'.");
                }
                $graph[$depId] = $graph[$depId] ?? [];
                $graph[$depId][] = $jobId; // dep -> job
                $inDegree[$jobId] = ($inDegree[$jobId] ?? 0) + 1;
                $inDegree[$depId] = $inDegree[$depId] ?? 0;
            }
        }

        // Kahn's algorithm
        $queue = [];
        foreach ($inDegree as $node => $deg) {
            if ($deg === 0) {
                $queue[] = $node;
            }
        }

        $ordered = [];
        while ($queue) {
            $node = array_shift($queue);
            $ordered[] = $idToJob[$node];
            foreach ($graph[$node] ?? [] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        if (count($ordered) !== count($jobs)) {
            throw new \RuntimeException('Cyclic dependency detected among jobs.');
        }

        return $ordered;
    }

    public function setIo(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): void
    {
        $this->context->setIo($input, $output);
    }

    public function getName(): string {
        return $this->name;
    }

    public function getJobs(): array {
        return $this->jobs;
    }
    public function getVersion(): string {
        return $this->version;
    }
    public function getContext(): Context {
        return $this->context;
    }

    public function getRunId(): string {
        return $this->runId;
    }

    public function setRunId(string $runId): void {
        $this->runId = $runId;
    }

    public function setVersion(string $version): void {
        $this->version = $version;
    }
    public function setVerbose(string $verbose): void {
        $this->verbose = $verbose;
        $this->context->setVerbose($verbose);

    }
    public function setTrigger(string $trigger): void {
        $this->trigger = $trigger;
    }
}