<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Validation;

use JsonException;
use stdClass;

/**
 * PHP の値を JSON Schema が期待する形に揃える。
 *
 * opis/json-schema は連想配列をオブジェクトとして扱わないため、
 * 検証前に stdClass へ変換する。
 */
final class JsonValue
{
    /**
     * 静的メソッドのみを提供するため、インスタンス化を禁止する。
     */
    private function __construct() {}

    /**
     * 値を JSON 表現へ正規化する。
     *
     * @param  mixed  $value  正規化対象の値
     * @return mixed 連想配列が stdClass に変換された値
     * @throws JsonException エンコードまたはデコードに失敗した場合
     */
    public static function normalize(mixed $value): mixed
    {
        // stdClass であっても入れ子に連想配列を含みうるため、走査を省略しない
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return json_decode(
            json_encode($value, JSON_THROW_ON_ERROR),
            associative: false,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /**
     * トップレベルがオブジェクトであることが確定している入力を正規化する。
     *
     * クエリパラメータやリクエストボディのように、空でもオブジェクトとして
     * 扱うべき値に使う。素の json_encode では空配列が JSON の [] に化けるため。
     *
     * @param  array<array-key, mixed>  $value  正規化対象の連想配列
     * @return stdClass 正規化されたオブジェクト
     * @throws JsonException エンコードまたはデコードに失敗した場合
     */
    public static function normalizeObject(array $value): stdClass
    {
        $normalized = self::normalize((object) $value);

        return $normalized instanceof stdClass ? $normalized : new stdClass;
    }
}
