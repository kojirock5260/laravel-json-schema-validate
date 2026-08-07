<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Console;

use cebe\openapi\spec\RequestBody;
use cebe\openapi\spec\Response as SpecResponse;
use Illuminate\Console\Command;
use Kojirock5260\JsonSchemaValidate\Observation\ObservationStore;
use Kojirock5260\JsonSchemaValidate\Observation\SchemaDiff;
use Kojirock5260\JsonSchemaValidate\Spec\OperationResolver;
use Kojirock5260\JsonSchemaValidate\Spec\ResolvedOperation;
use Kojirock5260\JsonSchemaValidate\Spec\SpecSchema;

/**
 * 記録した観測と spec を突き合わせ、食い違いを一覧するコマンド。
 */
final class DiffSpecCommand extends Command
{
    /**
     * コマンドの呼び出し名。
     *
     * @var string
     */
    protected $signature = 'openapi:diff
        {--strict : Exit with a failure code when differences are found}
        {--forget : Remove the recorded observations after reporting}';

    /**
     * コマンドの説明。
     *
     * @var string
     */
    protected $description = 'Compare recorded traffic with the OpenAPI document and list the differences';

    /**
     * コマンドを実行する。
     *
     * @param  ObservationStore  $store  観測結果の保存先
     * @param  OperationResolver  $resolver  spec 上の Operation の引き当て
     * @return int 終了コード
     */
    public function handle(ObservationStore $store, OperationResolver $resolver): int
    {
        $merged = $store->merged();

        if ($merged === []) {
            $this->components->info('No observations have been recorded.');

            return self::SUCCESS;
        }

        $found = 0;

        foreach ($merged as $entry) {
            $found += $this->report($resolver, $entry);
        }

        if ($this->option('forget')) {
            $store->forget();
        }

        if ($found === 0) {
            $this->components->info('The recorded traffic matches the specification.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->warn("Found {$found} difference(s) between the recorded traffic and the specification.");

        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Operation 1件について食い違いを出力する。
     *
     * @param  OperationResolver  $resolver  spec 上の Operation の引き当て
     * @param  array{path: string, method: string, request: array<string, mixed>, responses: array<int, array<string, mixed>>}  $entry  まとめられた観測
     * @return int 出力した食い違いの件数
     */
    private function report(OperationResolver $resolver, array $entry): int
    {
        $operation = $resolver->locate($entry['path'], $entry['method']);
        $title = strtoupper($entry['method']).' '.$entry['path'];

        if (! $operation instanceof ResolvedOperation) {
            $this->line("<fg=yellow>{$title}</>");
            $this->line('  the specification defines no such operation');

            return 1;
        }

        $sections = ['request' => SchemaDiff::compare($entry['request'], $this->requestSchema($operation))];

        foreach ($entry['responses'] as $status => $shape) {
            $sections["response {$status}"] = SchemaDiff::compare($shape, $this->responseSchema($operation, $status));
        }

        $sections = array_filter($sections);

        if ($sections === []) {
            return 0;
        }

        $this->line("<fg=yellow>{$title}</>");
        $count = 0;

        foreach ($sections as $label => $differences) {
            $this->line("  <fg=gray>{$label}</>");

            foreach ($differences as $path => $messages) {
                foreach ($messages as $message) {
                    $this->line("    <fg=cyan>{$path}</>  {$message}");
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * spec 上のリクエストボディのスキーマを返す。
     *
     * @param  ResolvedOperation  $operation  対象の Operation
     * @return array<string, mixed>|null スキーマ。定義が無ければ null
     */
    private function requestSchema(ResolvedOperation $operation): ?array
    {
        $requestBody = $operation->operation->requestBody;

        if (! $requestBody instanceof RequestBody) {
            return null;
        }

        return $this->jsonSchemaFrom($requestBody->content);
    }

    /**
     * spec 上のレスポンスボディのスキーマを返す。
     *
     * @param  ResolvedOperation  $operation  対象の Operation
     * @param  int  $status  ステータスコード
     * @return array<string, mixed>|null スキーマ。定義が無ければ null
     */
    private function responseSchema(ResolvedOperation $operation, int $status): ?array
    {
        $response = $operation->responseFor($status);

        return $response instanceof SpecResponse ? $this->jsonSchemaFrom($response->content) : null;
    }

    /**
     * content から JSON のスキーマを取り出す。
     *
     * @param  array<string, mixed>  $content  spec 上の content
     * @return array<string, mixed>|null スキーマ。取り出せなければ null
     */
    private function jsonSchemaFrom(array $content): ?array
    {
        foreach ($content as $mediaType => $definition) {
            if (str_contains((string) $mediaType, 'json') && is_object($definition)) {
                return SchemaDiff::fromSpec(SpecSchema::toJsonSchema($definition->schema ?? null));
            }
        }

        return null;
    }
}
