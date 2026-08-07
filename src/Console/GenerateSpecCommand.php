<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Console;

use Illuminate\Console\Command;
use JsonException;
use Kojirock5260\JsonSchemaValidate\Observation\ObservationStore;
use Kojirock5260\JsonSchemaValidate\Spec\SpecGenerator;
use Symfony\Component\Yaml\Yaml;

/**
 * 記録した観測から OpenAPI ドキュメントを書き出すコマンド。
 */
final class GenerateSpecCommand extends Command
{
    /**
     * コマンドの呼び出し名。
     *
     * @var string
     */
    protected $signature = 'openapi:generate
        {--output= : Write the document to this file instead of standard output}
        {--format=yaml : Output format, yaml or json}
        {--title=Generated from observed traffic : Value for info.title}
        {--api-version=1.0.0 : Value for info.version}';

    /**
     * コマンドの説明。
     *
     * @var string
     */
    protected $description = 'Build an OpenAPI document from the recorded traffic';

    /**
     * コマンドを実行する。
     *
     * @param  ObservationStore  $store  観測結果の保存先
     * @return int 終了コード
     */
    public function handle(ObservationStore $store): int
    {
        $merged = $store->merged();

        if ($merged === []) {
            $this->components->error('No observations have been recorded.');

            return self::FAILURE;
        }

        $document = (new SpecGenerator(
            $this->stringOption('title'),
            $this->stringOption('api-version'),
        ))->generate($merged);

        try {
            $rendered = $this->render($document);
        } catch (JsonException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        return $this->write($rendered, count($document['paths']));
    }

    /**
     * ドキュメントを指定された形式の文字列にする。
     *
     * @param  array<string, mixed>  $document  OpenAPI ドキュメント
     * @return string 書き出す文字列
     * @throws JsonException JSON への変換に失敗した場合
     */
    private function render(array $document): string
    {
        if ($this->option('format') === 'json') {
            return json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        }

        return $this->quoteNumericKeys(Yaml::dump($document, 20, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE));
    }

    /**
     * 数値だけのキーに引用符を付ける。
     *
     * OpenAPI と JSON のキーは常に文字列だが、PHP の配列では整数になるため、
     * そのまま YAML へ書き出すと整数キーとして解釈される。
     *
     * @param  string  $yaml  書き出す YAML
     * @return string キーを文字列に直した YAML
     */
    private function quoteNumericKeys(string $yaml): string
    {
        return preg_replace('/^(\s+)(\d+):$/m', "$1'$2':", $yaml) ?? $yaml;
    }

    /**
     * 指定を文字列として取り出す。
     *
     * @param  string  $name  指定の名前
     * @return string 指定された文字列
     */
    private function stringOption(string $name): string
    {
        $value = $this->option($name);

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * 書き出し先へ出力する。
     *
     * @param  string  $rendered  書き出す文字列
     * @param  int  $pathCount  書き出したパスの数
     * @return int 終了コード
     */
    private function write(string $rendered, int $pathCount): int
    {
        $output = $this->option('output');

        if ($output === null) {
            $this->line($rendered);

            return self::SUCCESS;
        }

        $path = $this->stringOption('output');
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            $this->components->error("The document could not be written to [{$path}].");

            return self::FAILURE;
        }

        if (file_put_contents($path, $rendered) === false) {
            $this->components->error("The document could not be written to [{$path}].");

            return self::FAILURE;
        }

        $this->components->info("Wrote {$pathCount} path(s) to [{$path}].");

        return self::SUCCESS;
    }
}
