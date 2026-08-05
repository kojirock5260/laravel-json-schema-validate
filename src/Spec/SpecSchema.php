<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Spec;

use cebe\openapi\spec\Schema;
use JsonException;
use Kojirock5260\JsonSchemaValidate\Validation\JsonValue;

/**
 * spec 上の Schema を、検証器へ渡せる JSON Schema へ変換する。
 */
final class SpecSchema
{
    /**
     * 静的メソッドのみを提供するため、インスタンス化を禁止する。
     */
    private function __construct() {}

    /**
     * Schema を stdClass へ変換する。
     *
     * パーサは属性として定義されていないキーワードを連想配列のまま保持するため、
     * 入れ子まで含めて stdClass へ揃えたうえで返す。
     *
     * @param  mixed  $schema  spec 上の Schema、または未定義を表す null
     * @return object|null 変換された JSON Schema。変換できなければ null
     *
     * @throws JsonException 変換に失敗した場合
     */
    public static function toJsonSchema(mixed $schema): ?object
    {
        if (! $schema instanceof Schema) {
            return null;
        }

        $normalized = JsonValue::normalize($schema->getSerializableData());

        return is_object($normalized) ? $normalized : null;
    }
}
