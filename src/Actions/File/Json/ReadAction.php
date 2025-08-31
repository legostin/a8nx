<?php

namespace A8nx\Actions\File\Json;

use A8nx\Context\Context;

class ReadAction extends \A8nx\Actions\File\ReadAction
{
    public function execute(array $params, Context &$context): array
    {
        $data = parent::execute($params, $context);
        $data['decoded'] = json_decode($data['body'], true);
        $context->getLogger()->debug('Read action result: ' . print_r($data['decoded'], true));
        return $data;
    }
}