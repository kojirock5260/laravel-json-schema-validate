<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Validation;

/**
 * パス・クエリ・ヘッダの値を、スキーマが宣言する型へ変換する。
 *
 * これらの値は HTTP 上では常に文字列として届くため、
 * type: integer などと突き合わせる前に変換する。
 * 宣言された型に変換できない値はそのまま返し、検証側で不正と判定させる。
 */
final class ParameterCoercer
{
    /**
     * 静的メソッドのみを提供するため、インスタンス化を禁止する。
     */
    private function __construct() {}

    /**
     * 値をスキーマの型へ変換する。
     *
     * @param  mixed  $value  変換対象の値
     * @param  object|bool  $schema  stdClass 化済みのスキーマ
     * @return mixed 変換後の値。変換できなければ元の値
     */
    public static function coerce(mixed $value, object|bool $schema): mixed
    {
        if (is_bool($schema)) {
            return $value;
        }

        $types = self::declaredTypes($schema);

        if (is_array($value)) {
            return self::coerceList($value, $schema, $types);
        }

        if (! is_string($value)) {
            return $value;
        }

        foreach ($types as $type) {
            $coerced = self::coerceScalar($value, $type);

            if ($coerced !== null) {
                return $coerced;
            }
        }

        return $value;
    }

    /**
     * スキーマが宣言する型の一覧を返す。
     *
     * 2020-12 では type に配列を書けるため、常に配列へ揃える。
     *
     * @param  object  $schema  stdClass 化済みのスキーマ
     * @return list<string> 宣言された型名の一覧
     */
    private static function declaredTypes(object $schema): array
    {
        $type = $schema->type ?? null;

        if (is_string($type)) {
            return [$type];
        }

        if (is_array($type)) {
            return array_values(array_filter($type, is_string(...)));
        }

        return [];
    }

    /**
     * 配列の各要素を items スキーマへ従って変換する。
     *
     * @param  array<array-key, mixed>  $value  変換対象の配列
     * @param  object  $schema  stdClass 化済みのスキーマ
     * @param  list<string>  $types  スキーマが宣言する型の一覧
     * @return array<array-key, mixed> 変換後の配列
     */
    private static function coerceList(array $value, object $schema, array $types): array
    {
        if (! in_array('array', $types, true)) {
            return $value;
        }

        $items = $schema->items ?? null;

        if (! is_object($items) && ! is_bool($items)) {
            return $value;
        }

        return array_map(static fn (mixed $item): mixed => self::coerce($item, $items), $value);
    }

    /**
     * 文字列を単一の型へ変換する。
     *
     * @param  string  $value  変換対象の文字列
     * @param  string  $type  変換先の型名
     * @return mixed 変換後の値。変換できなければ null
     */
    private static function coerceScalar(string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : null,
            'number' => is_numeric($value) && is_finite((float) $value) ? (float) $value : null,
            'boolean' => match ($value) {
                'true' => true,
                'false' => false,
                default => null,
            },
            'string' => $value,
            default => null,
        };
    }
}
