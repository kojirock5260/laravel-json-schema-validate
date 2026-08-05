<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Observation;

/**
 * 1回の往復で観測したリクエストとレスポンスの形を表す。
 */
final readonly class Observation
{
    /**
     * @param  string  $path  spec 上のパステンプレート
     * @param  string  $method  小文字の HTTP メソッド
     * @param  int  $status  レスポンスのステータスコード
     * @param  array<string, mixed>|null  $requestBody  リクエストボディの形。観測できなければ null
     * @param  array<string, mixed>|null  $responseBody  レスポンスボディの形。観測できなければ null
     * @param  array<string, mixed>|null  $query  クエリパラメータの形。観測できなければ null
     */
    public function __construct(
        public string $path,
        public string $method,
        public int $status,
        public ?array $requestBody = null,
        public ?array $responseBody = null,
        public ?array $query = null,
    ) {}

    /**
     * Operation を一意に表す文字列を返す。
     *
     * @return string 例: "get /members"
     */
    public function key(): string
    {
        return $this->method.' '.$this->path;
    }

    /**
     * 保存用の連想配列へ変換する。
     *
     * @return array<string, mixed> 保存用の表現
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'method' => $this->method,
            'status' => $this->status,
            'request' => $this->requestBody,
            'response' => $this->responseBody,
            'query' => $this->query,
        ];
    }

    /**
     * 保存された連想配列から復元する。
     *
     * @param  mixed  $data  復元元の値
     * @return self|null 復元された観測。形が合わなければ null
     */
    public static function fromArray(mixed $data): ?self
    {
        if (! is_array($data) || ! is_string($data['path'] ?? null) || ! is_string($data['method'] ?? null)) {
            return null;
        }

        if (! is_int($data['status'] ?? null)) {
            return null;
        }

        return new self(
            $data['path'],
            $data['method'],
            $data['status'],
            is_array($data['request'] ?? null) ? $data['request'] : null,
            is_array($data['response'] ?? null) ? $data['response'] : null,
            is_array($data['query'] ?? null) ? $data['query'] : null,
        );
    }
}
