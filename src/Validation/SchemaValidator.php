<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Validation;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * JSON Schema 2020-12 による検証を行う。
 *
 * OpenAPI 3.1 の Schema Object は JSON Schema 2020-12 そのものなので、
 * 方言変換を挟まずスキーマをそのまま opis へ渡す。
 */
final readonly class SchemaValidator
{
    /**
     * 1回の検証で収集するエラーの上限。
     *
     * opis の既定値は 1 で、最初のエラーだけを報告して打ち切る。
     * 不正な項目をまとめて返すために引き上げる。
     */
    private const int MAX_ERRORS = 100;

    /**
     * @param  Validator  $validator  opis の検証器
     * @param  ErrorFormatter  $formatter  検証エラーの整形器
     */
    public function __construct(
        private Validator $validator = new Validator(max_errors: self::MAX_ERRORS),
        private ErrorFormatter $formatter = new ErrorFormatter,
    ) {}

    /**
     * データをスキーマで検証し、エラーを返す。
     *
     * @param  mixed  $data  検証対象のデータ（JsonValue で正規化済みであること）
     * @param  object|bool  $schema  JSON Schema 2020-12 のスキーマ
     * @return array<string, list<string>> データポインタをキーにしたメッセージ一覧。妥当なら空配列
     */
    public function errors(mixed $data, object|bool $schema): array
    {
        $error = $this->validator->validate($data, $schema)->error();

        if ($error === null) {
            return [];
        }

        /** @var array<string, list<string>> */
        return $this->formatter->formatKeyed($error);
    }

    /**
     * データがスキーマに適合するかを判定する。
     *
     * @param  mixed  $data  検証対象のデータ（JsonValue で正規化済みであること）
     * @param  object|bool  $schema  JSON Schema 2020-12 のスキーマ
     * @return bool 適合すれば true
     */
    public function isValid(mixed $data, object|bool $schema): bool
    {
        return $this->validator->validate($data, $schema)->isValid();
    }
}
