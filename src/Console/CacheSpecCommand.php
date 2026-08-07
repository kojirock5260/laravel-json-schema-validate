<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Console;

use Illuminate\Console\Command;
use Kojirock5260\JsonSchemaValidate\Exception\SpecException;
use Kojirock5260\JsonSchemaValidate\Spec\SpecRepository;

/**
 * OpenAPI ドキュメントをパースし、結果をキャッシュファイルへ書き出すコマンド。
 */
final class CacheSpecCommand extends Command
{
    /**
     * コマンドの呼び出し名。
     *
     * @var string
     */
    protected $signature = 'openapi:cache';

    /**
     * コマンドの説明。
     *
     * @var string
     */
    protected $description = 'Parse the OpenAPI document and write the result to the cache file';

    /**
     * コマンドを実行する。
     *
     * @param  SpecRepository  $repository  OpenAPI ドキュメントの供給元
     * @return int 終了コード
     */
    public function handle(SpecRepository $repository): int
    {
        try {
            $repository->forgetCache();
            $path = $repository->cache();
        } catch (SpecException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("OpenAPI document cached to [{$path}].");

        return self::SUCCESS;
    }
}
