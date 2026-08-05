<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Validation;

/**
 * Content-Type ヘッダと spec の content 定義を突き合わせる。
 */
final class MediaTypeMatcher
{
    /**
     * 静的メソッドのみを提供するため、インスタンス化を禁止する。
     */
    private function __construct() {}

    /**
     * ヘッダに対応する content のキーを返す。
     *
     * 完全一致を優先し、見つからなければワイルドカード指定を探す。
     *
     * @param  list<string>  $available  spec が定義するメディアタイプの一覧
     * @param  string|null  $header  Content-Type ヘッダの値
     * @return string|null 対応するキー。見つからなければ null
     */
    public static function match(array $available, ?string $header): ?string
    {
        $type = self::normalize($header);

        if ($type === null) {
            return null;
        }

        foreach ($available as $candidate) {
            if (strtolower($candidate) === $type) {
                return $candidate;
            }
        }

        $primary = explode('/', $type, 2)[0];

        foreach ($available as $candidate) {
            if (in_array(strtolower($candidate), [$primary.'/*', '*/*'], true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * メディアタイプが JSON を表すかどうかを判定する。
     *
     * application/json のほか、application/problem+json のような
     * +json で終わるサブタイプも JSON として扱う。
     *
     * @param  string|null  $mediaType  判定するメディアタイプ
     * @return bool JSON なら true
     */
    public static function isJson(?string $mediaType): bool
    {
        $type = self::normalize($mediaType);

        if ($type === null) {
            return false;
        }

        return $type === 'application/json' || str_ends_with($type, '+json');
    }

    /**
     * メディアタイプから引数部分を落とし、小文字へ揃える。
     *
     * @param  string|null  $mediaType  正規化するメディアタイプ
     * @return string|null 正規化された文字列。空なら null
     */
    private static function normalize(?string $mediaType): ?string
    {
        if ($mediaType === null) {
            return null;
        }

        $normalized = strtolower(trim(explode(';', $mediaType, 2)[0]));

        return $normalized === '' ? null : $normalized;
    }
}
