<?php

namespace A8nx\Actions;

use A8nx\Context\Context;

interface ActionInterface
{
    public function execute(array $params, Context &$context, array $steps = [] ): array;
}