<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate;

interface SchemaInterface
{
    /**
     * Get Schema.
     * @return array
     */
    public static function getSchema(): array;
}
