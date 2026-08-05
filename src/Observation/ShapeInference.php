<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Observation;

use stdClass;

/**
 * 観測した値から JSON Schema の骨組みを作る。
 *
 * 記録するのは型と構造だけで、値そのものは残さない。
 */
final class ShapeInference
{
    /**
     * 静的メソッドのみを提供するため、インスタンス化を禁止する。
     */
    private function __construct() {}

    /**
     * 値に対応するスキーマを返す。
     *
     * @param  mixed  $value  観測した値（JsonValue で正規化済みであること）
     * @return array<string, mixed> 推論されたスキーマ
     */
    public static function fromValue(mixed $value): array
    {
        return match (true) {
            $value === null => ['type' => 'null'],
            is_bool($value) => ['type' => 'boolean'],
            is_int($value) => ['type' => 'integer'],
            is_float($value) => ['type' => 'number'],
            is_string($value) => ['type' => 'string'],
            is_array($value) => self::fromList($value),
            $value instanceof stdClass => self::fromObject($value),
            default => [],
        };
    }

    /**
     * 配列に対応するスキーマを返す。
     *
     * 要素は全件をマージして items にまとめる。空配列なら items を持たない。
     *
     * @param  array<array-key, mixed>  $value  観測した配列
     * @return array<string, mixed> 推論されたスキーマ
     */
    private static function fromList(array $value): array
    {
        if ($value === []) {
            return ['type' => 'array'];
        }

        $items = ShapeMerger::mergeAll(array_map(self::fromValue(...), array_values($value)));

        return $items === [] ? ['type' => 'array'] : ['type' => 'array', 'items' => $items];
    }

    /**
     * オブジェクトに対応するスキーマを返す。
     *
     * 観測できたキーはすべて required として記録する。
     * 欠けうるキーはマージの際に交差を取ることで required から外れる。
     *
     * @param  stdClass  $value  観測したオブジェクト
     * @return array<string, mixed> 推論されたスキーマ
     */
    private static function fromObject(stdClass $value): array
    {
        $properties = [];

        foreach (get_object_vars($value) as $name => $property) {
            $properties[(string) $name] = self::fromValue($property);
        }

        if ($properties === []) {
            return ['type' => 'object'];
        }

        return [
            'type' => 'object',
            'required' => array_keys($properties),
            'properties' => $properties,
        ];
    }
}
