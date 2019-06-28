<?php

declare(strict_types=1);

namespace Kojirock\JsonSchemaValidate;

interface SchemaInterface
{
    /**
     * Get Schema
     * @return array
     */
    public static function getSchema(): array;
}
