<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Observation;

use JsonException;

/**
 * 観測結果を 1 行 1 件の JSON として追記し、読み出し時にまとめる。
 */
final readonly class ObservationStore
{
    /**
     * @param  string  $path  観測結果を書き出すファイルのパス
     */
    public function __construct(
        private string $path,
    ) {}

    /**
     * 観測結果を 1 件追記する。
     *
     * @param  Observation  $observation  追記する観測
     */
    public function record(Observation $observation): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            return;
        }

        try {
            $line = json_encode($observation->toArray(), JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return;
        }

        file_put_contents($this->path, $line.PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * 保存された観測をすべて読み出す。
     *
     * 壊れた行は読み飛ばす。
     *
     * @return list<Observation> 読み出した観測の一覧
     */
    public function all(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $contents = file_get_contents($this->path);

        if ($contents === false) {
            return [];
        }

        $observations = [];

        foreach (explode(PHP_EOL, $contents) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $observation = Observation::fromArray(json_decode($line, true));

            if ($observation instanceof Observation) {
                $observations[] = $observation;
            }
        }

        return $observations;
    }

    /**
     * 観測を Operation ごとにまとめる。
     *
     * リクエストは Operation 単位で、レスポンスはステータスコード単位でまとめる。
     *
     * @return array<string, array{path: string, method: string, query: array<string, mixed>, request: array<string, mixed>, responses: array<int, array<string, mixed>>}>
     */
    public function merged(): array
    {
        $merged = [];

        foreach ($this->all() as $observation) {
            $key = $observation->key();

            $merged[$key] ??= [
                'path' => $observation->path,
                'method' => $observation->method,
                'query' => [],
                'request' => [],
                'responses' => [],
            ];

            $merged[$key]['query'] = ShapeMerger::merge(
                $merged[$key]['query'],
                $observation->query ?? ['type' => 'object'],
            );

            if ($observation->requestBody !== null) {
                $merged[$key]['request'] = ShapeMerger::merge(
                    $merged[$key]['request'],
                    $observation->requestBody,
                );
            }

            if ($observation->responseBody !== null) {
                $merged[$key]['responses'][$observation->status] = ShapeMerger::merge(
                    $merged[$key]['responses'][$observation->status] ?? [],
                    $observation->responseBody,
                );
            }
        }

        ksort($merged);

        foreach ($merged as $key => $entry) {
            ksort($entry['responses']);
            $merged[$key] = $entry;
        }

        return $merged;
    }

    /**
     * 保存されたファイルを削除する。
     *
     * @return bool 削除した場合は true。存在しなかった場合は false
     */
    public function forget(): bool
    {
        return is_file($this->path) && unlink($this->path);
    }
}
