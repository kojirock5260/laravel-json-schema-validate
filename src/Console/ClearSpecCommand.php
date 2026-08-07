<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Console;

use Illuminate\Console\Command;
use Kojirock5260\JsonSchemaValidate\Spec\SpecRepository;

/**
 * OpenAPI ドキュメントのキャッシュファイルを削除するコマンド。
 */
final class ClearSpecCommand extends Command
{
    /**
     * コマンドの呼び出し名。
     *
     * @var string
     */
    protected $signature = 'openapi:clear';

    /**
     * コマンドの説明。
     *
     * @var string
     */
    protected $description = 'Remove the cached OpenAPI document';

    /**
     * コマンドを実行する。
     *
     * @param  SpecRepository  $repository  OpenAPI ドキュメントの供給元
     * @return int 終了コード
     */
    public function handle(SpecRepository $repository): int
    {
        $this->components->info($repository->forgetCache()
            ? 'The cached OpenAPI document was removed.'
            : 'There was no cached OpenAPI document.');

        return self::SUCCESS;
    }
}
