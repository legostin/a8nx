<?php

namespace A8nx;

use A8nx\Actions\ActionInterface;
use A8nx\Context\Context;
use A8nx\Context\Resolver;

class Step
{
    private string $id;
    private ActionInterface $action;
    /**
     * @var string[]
     */
    private array $needs = [];

    private array $with = [];

    private mixed $forEach = [];

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getAction(): ActionInterface
    {
        return $this->action;
    }

    public function setAction(ActionInterface $action): void
    {
        $this->action = $action;
    }

    public function getWith(): array
    {
        return $this->with;
    }

    public function setWith(array $with): void
    {
        $this->with = $with;
    }

    public function setWithParam(string $param, mixed $value): void
    {
        $this->with[$param] = $value;
    }

    public function getNeeds(): array {
        return $this->needs;
    }

    public function setNeeds(array $needs): void {
        $this->needs = $needs;
    }

    public function getForEach(): mixed
    {
        return $this->forEach;
    }

    public function setForEach(mixed $forEach): void
    {
        $this->forEach = $forEach;
    }


    public function run(Context &$context): int {
        $context->getLogger()->info('Running step ' . $this->id);
        $data = Resolver::resolve($this->with, $context);

        if($this->forEach) {
            $items = Resolver::resolve($this->forEach, $context);
            $result = [];

            foreach ($items as $i => $item) {
                $item = [
                    'iteration' => $i,
                    'data' => $item,
                ];

                $context->set('item', $item);
                $data = Resolver::resolve($this->with, $context);

                $context->getLogger()->info(sprintf("Running step iteration %s (%d/%d)", $this->getId(),$i+1, count($items)));
                $result[] = $this->action->execute($data, $context);
                $context->getLogger()->info(sprintf("Finished step iteration %s (%d/%d)", $this->getId(),$i+1, count($items)));
            }
        } else {
            $result = $this->action->execute($data, $context);
        }

        $context->set("step.{$this->getId()}", $result);
        $context->getLogger()->info('Finished step ' . $this->id);
        return 0;
    }

}