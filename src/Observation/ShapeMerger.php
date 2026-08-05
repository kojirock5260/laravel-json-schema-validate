<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Observation;

/**
 * 複数回の観測から得たスキーマを1つにまとめる。
 *
 * 型は和を取り、required は積を取る。
 * 一度でも欠けたキーは required から外れ、複数の型が現れた項目は union になる。
 */
final class ShapeMerger
{
    /**
     * 静的メソッドのみを提供するため、インスタンス化を禁止する。
     */
    private function __construct() {}

    /**
     * スキーマの一覧をまとめて1つにする。
     *
     * @param  list<array<string, mixed>>  $schemas  まとめる対象のスキーマ
     * @return array<string, mixed> まとめられたスキーマ
     */
    public static function mergeAll(array $schemas): array
    {
        return array_reduce($schemas, self::merge(...), []);
    }

    /**
     * 2つのスキーマをまとめる。
     *
     * @param  array<string, mixed>  $left  まとめ元のスキーマ
     * @param  array<string, mixed>  $right  まとめるスキーマ
     * @return array<string, mixed> まとめられたスキーマ
     */
    public static function merge(array $left, array $right): array
    {
        if ($left === []) {
            return $right;
        }

        if ($right === []) {
            return $left;
        }

        $merged = [];

        $types = self::mergeTypes($left, $right);

        if ($types !== []) {
            $merged['type'] = count($types) === 1 ? $types[0] : $types;
        }

        $required = self::mergeRequired($left, $right);

        if ($required !== []) {
            $merged['required'] = $required;
        }

        $properties = self::mergeProperties($left, $right);

        if ($properties !== []) {
            $merged['properties'] = $properties;
        }

        $items = self::mergeItems($left, $right);

        if ($items !== []) {
            $merged['items'] = $items;
        }

        return $merged;
    }

    /**
     * 双方の型の和を返す。
     *
     * @param  array<string, mixed>  $left  まとめ元のスキーマ
     * @param  array<string, mixed>  $right  まとめるスキーマ
     * @return list<string> 型名の一覧
     */
    private static function mergeTypes(array $left, array $right): array
    {
        $types = array_unique([...self::types($left), ...self::types($right)]);
        sort($types);

        return $types;
    }

    /**
     * スキーマが宣言する型を一覧で返す。
     *
     * @param  array<string, mixed>  $schema  対象のスキーマ
     * @return list<string> 型名の一覧
     */
    private static function types(array $schema): array
    {
        $type = $schema['type'] ?? null;

        if (is_string($type)) {
            return [$type];
        }

        if (is_array($type)) {
            return array_values(array_filter($type, is_string(...)));
        }

        return [];
    }

    /**
     * 双方の required の積を返す。
     *
     * @param  array<string, mixed>  $left  まとめ元のスキーマ
     * @param  array<string, mixed>  $right  まとめるスキーマ
     * @return list<string> 双方に現れたキーの一覧
     */
    private static function mergeRequired(array $left, array $right): array
    {
        $leftRequired = self::stringList($left['required'] ?? null);
        $rightRequired = self::stringList($right['required'] ?? null);

        return array_values(array_intersect($leftRequired, $rightRequired));
    }

    /**
     * 双方の properties を、同名のものは再帰的にまとめて返す。
     *
     * @param  array<string, mixed>  $left  まとめ元のスキーマ
     * @param  array<string, mixed>  $right  まとめるスキーマ
     * @return array<string, array<string, mixed>> まとめられた properties
     */
    private static function mergeProperties(array $left, array $right): array
    {
        $leftProperties = self::schemaMap($left['properties'] ?? null);
        $rightProperties = self::schemaMap($right['properties'] ?? null);

        $merged = $leftProperties;

        foreach ($rightProperties as $name => $schema) {
            $merged[$name] = isset($merged[$name]) ? self::merge($merged[$name], $schema) : $schema;
        }

        return $merged;
    }

    /**
     * 双方の items をまとめて返す。
     *
     * @param  array<string, mixed>  $left  まとめ元のスキーマ
     * @param  array<string, mixed>  $right  まとめるスキーマ
     * @return array<string, mixed> まとめられた items
     */
    private static function mergeItems(array $left, array $right): array
    {
        $leftItems = is_array($left['items'] ?? null) ? $left['items'] : [];
        $rightItems = is_array($right['items'] ?? null) ? $right['items'] : [];

        return self::merge($leftItems, $rightItems);
    }

    /**
     * 値を文字列の一覧として取り出す。
     *
     * @param  mixed  $value  取り出す対象
     * @return list<string> 文字列の一覧
     */
    private static function stringList(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }

    /**
     * 値をスキーマの連想配列として取り出す。
     *
     * @param  mixed  $value  取り出す対象
     * @return array<string, array<string, mixed>> スキーマの連想配列
     */
    private static function schemaMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_filter($value, is_array(...));
    }
}
