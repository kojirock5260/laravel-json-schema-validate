<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Spec;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use Kojirock5260\JsonSchemaValidate\Exception\SpecException;

/**
 * OpenAPI ドキュメントを読み込み、パース結果をインスタンス内に保持する。
 *
 * キャッシュファイルが指定されている場合は、パース済みのオブジェクトを
 * そこから復元する。元のドキュメントの更新時刻が変わっていればキャッシュは使わない。
 */
final class SpecRepository
{
    /**
     * パース済みのドキュメント。読み込み前は null。
     */
    private ?OpenApi $spec = null;

    /**
     * @param  string  $path  OpenAPI ドキュメントのパス
     * @param  string|null  $cachePath  キャッシュファイルのパス。使わない場合は null
     */
    public function __construct(
        private readonly string $path,
        private readonly ?string $cachePath = null,
    ) {}

    /**
     * OpenAPI ドキュメントを取得する。
     *
     * @return OpenApi パース済みのドキュメント
     * @throws SpecException ファイルが存在しない、または形式が非対応の場合
     */
    public function load(): OpenApi
    {
        if ($this->spec !== null) {
            return $this->spec;
        }

        return $this->spec = $this->loadFromCache() ?? $this->parse();
    }

    /**
     * ドキュメントをパースし、結果をキャッシュファイルへ書き出す。
     *
     * @return string 書き出したキャッシュファイルのパス
     * @throws SpecException キャッシュパスが未設定、または書き出しに失敗した場合
     */
    public function cache(): string
    {
        if ($this->cachePath === null) {
            throw SpecException::cachePathMissing();
        }

        $payload = serialize([
            'mtime' => $this->sourceModifiedAt(),
            'spec' => $this->parse(),
        ]);

        $directory = dirname($this->cachePath);

        if (! is_dir($directory) && ! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            throw SpecException::cacheNotWritable($this->cachePath);
        }

        if (file_put_contents($this->cachePath, $payload) === false) {
            throw SpecException::cacheNotWritable($this->cachePath);
        }

        return $this->cachePath;
    }

    /**
     * キャッシュファイルを削除する。
     *
     * @return bool 削除した場合は true。存在しなかった場合は false
     */
    public function forgetCache(): bool
    {
        if ($this->cachePath === null || ! is_file($this->cachePath)) {
            return false;
        }

        return unlink($this->cachePath);
    }

    /**
     * 使用できるキャッシュが存在するかを判定する。
     *
     * @return bool 存在すれば true
     */
    public function isCached(): bool
    {
        return $this->loadFromCache() instanceof OpenApi;
    }

    /**
     * ドキュメントが宣言する OpenAPI のバージョンを返す。
     *
     * @return string 例: "3.1.0"
     * @throws SpecException 読み込みに失敗した場合
     */
    public function version(): string
    {
        return $this->load()->openapi;
    }

    /**
     * ドキュメントが OpenAPI 3.1 系かどうかを判定する。
     *
     * @return bool 3.1 系なら true
     * @throws SpecException 読み込みに失敗した場合
     */
    public function isVersion31(): bool
    {
        return str_starts_with($this->version(), '3.1');
    }

    /**
     * ドキュメントをファイルから読み込んでパースする。
     *
     * @return OpenApi パース済みのドキュメント
     * @throws SpecException ファイルが存在しない、または形式が非対応の場合
     */
    private function parse(): OpenApi
    {
        $resolved = $this->resolvedPath();

        return match (strtolower(pathinfo($resolved, PATHINFO_EXTENSION))) {
            'json' => Reader::readFromJsonFile($resolved),
            'yaml', 'yml' => Reader::readFromYamlFile($resolved),
            default => throw SpecException::unsupportedFormat($resolved),
        };
    }

    /**
     * キャッシュファイルからパース済みのドキュメントを復元する。
     *
     * 元のドキュメントの更新時刻が記録と異なる場合は復元しない。
     *
     * @return OpenApi|null 復元されたドキュメント。復元できなければ null
     */
    private function loadFromCache(): ?OpenApi
    {
        if ($this->cachePath === null || ! is_file($this->cachePath)) {
            return null;
        }

        $contents = file_get_contents($this->cachePath);

        if ($contents === false) {
            return null;
        }

        $payload = @unserialize($contents);

        if (! is_array($payload) || ($payload['mtime'] ?? null) !== $this->sourceModifiedAt()) {
            return null;
        }

        return ($payload['spec'] ?? null) instanceof OpenApi ? $payload['spec'] : null;
    }

    /**
     * ドキュメントの絶対パスを返す。
     *
     * @return string 解決された絶対パス
     * @throws SpecException ファイルが存在しない場合
     */
    private function resolvedPath(): string
    {
        $resolved = realpath($this->path);

        if ($resolved === false) {
            throw SpecException::notFound($this->path);
        }

        return $resolved;
    }

    /**
     * ドキュメントの更新時刻を返す。
     *
     * @return int|null 更新時刻。取得できなければ null
     */
    private function sourceModifiedAt(): ?int
    {
        $mtime = @filemtime($this->path);

        return $mtime === false ? null : $mtime;
    }
}
