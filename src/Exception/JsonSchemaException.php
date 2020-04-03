<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Exception;

use JsonSchema\Validator;

class JsonSchemaException extends \RuntimeException
{
    /**
     * @var Validator
     */
    private $validator;

    /**
     * @param Validator $validator
     * @return JsonSchemaException
     */
    public static function newException(Validator $validator): self
    {
        $self            = new self('Json Schema Validate Error!');
        $self->validator = $validator;
        return $self;
    }

    /**
     * @return array
     */
    public function getSchemaErrors(): array
    {
        $results = [];
        $errors  = $this->validator->getErrors();
        foreach ($errors as $k => $error) {
            $results[$error['property']][] = $error['message'];
        }

        return $results;
    }
}
