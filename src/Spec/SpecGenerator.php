<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Spec;

use Symfony\Component\HttpFoundation\Response;

/**
 * 記録した観測から OpenAPI ドキュメントを組み立てる。
 *
 * 観測できた範囲だけを書き出すため、通っていない経路や
 * 一度も欠けなかったパラメータの扱いは実際と異なりうる。
 */
final readonly class SpecGenerator
{
    /**
     * @param  string  $title  info.title に入れる文字列
     * @param  string  $version  info.version に入れる文字列
     */
    public function __construct(
        private string $title = 'Generated from observed traffic',
        private string $version = '1.0.0',
    ) {}

    /**
     * まとめられた観測から OpenAPI ドキュメントを作る。
     *
     * @param  array<string, array{path: string, method: string, query: array<string, mixed>, request: array<string, mixed>, responses: array<int, array<string, mixed>>}>  $merged  まとめられた観測
     * @return array<string, mixed> OpenAPI ドキュメント
     */
    public function generate(array $merged): array
    {
        $paths = [];

        foreach ($merged as $entry) {
            $paths[$entry['path']] ??= [];
            $paths[$entry['path']][$entry['method']] = $this->operation($entry);
        }

        ksort($paths);

        return [
            'openapi' => '3.1.0',
            'info' => ['title' => $this->title, 'version' => $this->version],
            'paths' => $paths,
        ];
    }

    /**
     * 観測1件分の Operation を作る。
     *
     * @param  array{path: string, method: string, query: array<string, mixed>, request: array<string, mixed>, responses: array<int, array<string, mixed>>}  $entry  まとめられた観測
     * @return array<string, mixed> Operation
     */
    private function operation(array $entry): array
    {
        $operation = [];
        $parameters = [...$this->pathParameters($entry['path']), ...$this->queryParameters($entry['query'])];

        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }

        if ($entry['request'] !== []) {
            $operation['requestBody'] = [
                'required' => true,
                'content' => ['application/json' => ['schema' => $entry['request']]],
            ];
        }

        $operation['responses'] = $this->responses($entry['responses']);

        return $operation;
    }

    /**
     * パステンプレートの変数からパスパラメータを作る。
     *
     * @param  string  $path  パステンプレート
     * @return list<array<string, mixed>> パスパラメータの一覧
     */
    private function pathParameters(string $path): array
    {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);

        return array_map(static fn (string $name): array => [
            'name' => $name,
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => 'string'],
        ], $matches[1]);
    }

    /**
     * 観測したクエリパラメータから parameters を作る。
     *
     * すべての観測に現れたパラメータだけを required とする。
     *
     * @param  array<string, mixed>  $query  まとめられたクエリパラメータのスキーマ
     * @return list<array<string, mixed>> クエリパラメータの一覧
     */
    private function queryParameters(array $query): array
    {
        $properties = is_array($query['properties'] ?? null) ? $query['properties'] : [];
        $required = is_array($query['required'] ?? null) ? $query['required'] : [];
        $parameters = [];

        foreach ($properties as $name => $schema) {
            if (! is_array($schema)) {
                continue;
            }

            $parameters[] = [
                'name' => (string) $name,
                'in' => 'query',
                'required' => in_array($name, $required, true),
                'schema' => $schema,
            ];
        }

        return $parameters;
    }

    /**
     * 観測したレスポンスから responses を作る。
     *
     * PHP の配列は "200" を文字列キーとして保持できないため、キーは整数になる。
     * 書き出す際に引用符を付けて文字列へ戻す。
     *
     * @param  array<int, array<string, mixed>>  $responses  ステータスごとのスキーマ
     * @return array<array-key, mixed> responses
     */
    private function responses(array $responses): array
    {
        $built = [];

        foreach ($responses as $status => $schema) {
            $built[(string) $status] = array_filter([
                'description' => Response::$statusTexts[$status] ?? 'Response',
                'content' => $schema === [] ? null : ['application/json' => ['schema' => $schema]],
            ], static fn (mixed $value): bool => $value !== null);
        }

        return $built === [] ? ['default' => ['description' => 'Response']] : $built;
    }
}
