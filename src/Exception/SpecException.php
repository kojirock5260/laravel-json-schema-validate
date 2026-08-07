<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Exception;

use RuntimeException;

/**
 * OpenAPI ドキュメントの読み込みに失敗したことを表す例外。
 */
final class SpecException extends RuntimeException
{
    /**
     * 指定パスにファイルが存在しない場合の例外を生成する。
     *
     * @param  string  $path  読み込もうとしたパス
     * @return self 生成された例外
     */
    public static function notFound(string $path): self
    {
        return new self("OpenAPI document was not found at [{$path}].");
    }

    /**
     * 拡張子から形式を判別できない場合の例外を生成する。
     *
     * @param  string  $path  読み込もうとしたパス
     * @return self 生成された例外
     */
    public static function unsupportedFormat(string $path): self
    {
        return new self("Unsupported OpenAPI document format for [{$path}]. Use .json, .yaml or .yml.");
    }

    /**
     * キャッシュのパスが設定されていない場合の例外を生成する。
     *
     * @return self 生成された例外
     */
    public static function cachePathMissing(): self
    {
        return new self('No cache path is configured. Set [json-schema.cache] to enable caching.');
    }

    /**
     * キャッシュファイルを書き出せない場合の例外を生成する。
     *
     * @param  string  $path  書き出そうとしたパス
     * @return self 生成された例外
     */
    public static function cacheNotWritable(string $path): self
    {
        return new self("The OpenAPI cache could not be written to [{$path}].");
    }
}
