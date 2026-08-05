<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Observation;

/**
 * クエリパラメータの値から型を推し量る。
 *
 * 値は常に文字列で届くため、見た目から整数・真偽値・数値を判別する。
 * パラメータ全体を object のスキーマとして表すことで、
 * 名前の集合と型を ShapeMerger でまとめられるようにする。
 */
final class ParameterInference
{
    /**
     * 静的メソッドのみを提供するため、インスタンス化を禁止する。
     */
    private function __construct() {}

    /**
     * クエリパラメータ全体をオブジェクトのスキーマとして返す。
     *
     * @param  array<array-key, mixed>  $query  クエリパラメータ
     * @return array<string, mixed>|null 推論されたスキーマ。パラメータが無ければ null
     */
    public static function fromQuery(array $query): ?array
    {
        if ($query === []) {
            return null;
        }

        $properties = [];

        foreach ($query as $name => $value) {
            $properties[(string) $name] = self::fromValue($value);
        }

        return [
            'type' => 'object',
            'required' => array_keys($properties),
            'properties' => $properties,
        ];
    }

    /**
     * 1つの値から型を推し量る。
     *
     * @param  mixed  $value  クエリパラメータの値
     * @return array<string, mixed> 推論されたスキーマ
     */
    private static function fromValue(mixed $value): array
    {
        if (is_array($value)) {
            $items = ShapeMerger::mergeAll(array_map(self::fromValue(...), array_values($value)));

            return $items === [] ? ['type' => 'array'] : ['type' => 'array', 'items' => $items];
        }

        if (! is_string($value)) {
            return ShapeInference::fromValue($value);
        }

        return ['type' => self::scalarType($value)];
    }

    /**
     * 文字列の見た目から型名を決める。
     *
     * @param  string  $value  クエリパラメータの値
     * @return string 型名
     */
    private static function scalarType(string $value): string
    {
        return match (true) {
            preg_match('/^-?\d+$/', $value) === 1 => 'integer',
            in_array($value, ['true', 'false'], true) => 'boolean',
            is_numeric($value) => 'number',
            default => 'string',
        };
    }
}
