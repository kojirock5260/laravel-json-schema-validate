<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Validation;

/**
 * 検証エラーのキーを、Laravel の検証エラーと同じドット記法へ変換する。
 */
final class ErrorKey
{
    /**
     * 静的メソッドのみを提供するため、インスタンス化を禁止する。
     */
    private function __construct() {}

    /**
     * JSON Pointer をドット記法へ変換する。
     *
     * 例: "/data/0/name" と接頭辞 "" は "data.0.name" になる。
     * 指す先が根の場合は接頭辞のみを返す。
     *
     * @param  string  $pointer  opis が返した JSON Pointer
     * @param  string  $prefix  先頭に付ける名前。パラメータ名やボディの別名に使う
     * @return string ドット記法のキー
     */
    public static function fromPointer(string $pointer, string $prefix = ''): string
    {
        $path = trim($pointer, '/');

        $segments = $path === '' ? [] : array_map(
            static fn (string $segment): string => str_replace(['~1', '~0'], ['/', '~'], $segment),
            explode('/', $path),
        );

        if ($prefix !== '') {
            array_unshift($segments, $prefix);
        }

        return implode('.', $segments);
    }

    /**
     * エラー一覧のキーをまとめてドット記法へ変換する。
     *
     * @param  array<string, list<string>>  $errors  JSON Pointer をキーにしたエラー一覧
     * @param  string  $prefix  先頭に付ける名前
     * @param  string  $rootKey  変換結果が空になった場合に使う名前
     * @return array<string, list<string>> ドット記法をキーにしたエラー一覧
     */
    public static function rekey(array $errors, string $prefix = '', string $rootKey = ''): array
    {
        $rekeyed = [];

        foreach ($errors as $pointer => $messages) {
            $key = self::fromPointer($pointer, $prefix);

            if ($key === '') {
                $key = $rootKey;
            }

            $rekeyed[$key] = array_merge($rekeyed[$key] ?? [], $messages);
        }

        return $rekeyed;
    }

    /**
     * 2つのエラー一覧を、同じキーのメッセージを連結しながら統合する。
     *
     * @param  array<string, list<string>>  $left  統合元のエラー一覧
     * @param  array<string, list<string>>  $right  統合するエラー一覧
     * @return array<string, list<string>> 統合されたエラー一覧
     */
    public static function merge(array $left, array $right): array
    {
        foreach ($right as $key => $messages) {
            $left[$key] = array_merge($left[$key] ?? [], $messages);
        }

        return $left;
    }
}
