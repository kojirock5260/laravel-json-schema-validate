<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Exception;

use RuntimeException;

/**
 * レスポンスが OpenAPI の定義に適合しなかったことを表す例外。
 *
 * 原因は送信側ではなく実装にあるため、リクエストの検証失敗とは別に扱う。
 */
final class ResponseValidationException extends RuntimeException
{
    /**
     * @param  string  $operation  対象の Operation を表す識別子
     * @param  array<string, list<string>>  $errors  検証エラーの一覧
     * @param  string  $message  例外メッセージ
     */
    private function __construct(
        public readonly string $operation,
        public readonly array $errors,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * Operation と検証エラーから例外を生成する。
     *
     * @param  string  $operation  対象の Operation を表す識別子
     * @param  array<string, list<string>>  $errors  検証エラーの一覧
     * @return self 生成された例外
     */
    public static function forOperation(string $operation, array $errors): self
    {
        $summary = implode(', ', array_map(
            static fn (string $key, array $messages): string => $key.': '.implode(' / ', $messages),
            array_keys($errors),
            $errors,
        ));

        return new self(
            $operation,
            $errors,
            "The response for [{$operation}] does not match the specification. {$summary}",
        );
    }
}
