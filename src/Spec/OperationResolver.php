<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Spec;

use Illuminate\Routing\Route;
use Kojirock5260\JsonSchemaValidate\Exception\SpecException;

/**
 * Laravel のルートを OpenAPI の Operation に対応づける。
 *
 * OpenAPI の Path Templating は波括弧で変数部分を表すが、変数名は
 * ルート側と一致するとは限らない（Laravel の {member} と spec の
 * {memberId} など）。そのため変数名を無視した位置一致で突き合わせる。
 */
final readonly class OperationResolver
{
    /**
     * @param  SpecRepository  $spec  OpenAPI ドキュメントの供給元
     * @param  string  $basePath  ルート URI から取り除く接頭辞（例: "/api"）
     */
    public function __construct(
        private SpecRepository $spec,
        private string $basePath = '',
    ) {}

    /**
     * ルートとメソッドに対応する Operation を解決する。
     *
     * @param  Route  $route  対象の Laravel ルート
     * @param  string  $method  HTTP メソッド（大文字小文字は問わない）
     * @return ResolvedOperation|null 対応する Operation。spec に定義がなければ null
     * @throws SpecException OpenAPI ドキュメントの読み込みに失敗した場合
     */
    public function resolve(Route $route, string $method): ?ResolvedOperation
    {
        $method = strtolower($method);
        $target = $this->normalize($this->stripBasePath($route->uri()));

        foreach ($this->spec->load()->paths->getPaths() as $template => $pathItem) {
            if ($this->normalize($template) !== $target) {
                continue;
            }

            $operations = $pathItem->getOperations();

            if (! isset($operations[$method])) {
                return null;
            }

            return new ResolvedOperation(
                $template,
                $method,
                $operations[$method],
                $pathItem,
                $this->mapParameters($template, $route->uri()),
            );
        }

        return null;
    }

    /**
     * spec 側とルート側のパラメータ名を、出現順で対応づける。
     *
     * @param  string  $template  spec 上のパステンプレート
     * @param  string  $uri  Laravel のルート URI
     * @return array<string, string> spec 側の名前から Laravel 側の名前への対応表
     */
    private function mapParameters(string $template, string $uri): array
    {
        $specNames = $this->parameterNames($template);
        $routeNames = $this->parameterNames($uri);

        $map = [];

        foreach ($specNames as $index => $specName) {
            if (isset($routeNames[$index])) {
                $map[$specName] = $routeNames[$index];
            }
        }

        return $map;
    }

    /**
     * パスに含まれる波括弧の中身を出現順に取り出す。
     *
     * Laravel の任意パラメータを表す末尾の ? は取り除く。
     *
     * @param  string  $path  対象のパス
     * @return list<string> パラメータ名の一覧
     */
    private function parameterNames(string $path): array
    {
        preg_match_all('/\{([^}]*)\}/', $path, $matches);

        return array_map(static fn (string $name): string => rtrim($name, '?'), $matches[1]);
    }

    /**
     * ルートを spec のパステンプレートと同じ書式に整える。
     *
     * 変数名はルート側のものを残し、Laravel の任意パラメータを表す ? だけ取り除く。
     *
     * @param  Route  $route  対象の Laravel ルート
     * @return string パステンプレート
     */
    public function templateFor(Route $route): string
    {
        $trimmed = trim($this->stripBasePath($route->uri()), '/');

        if ($trimmed === '') {
            return '/';
        }

        return '/'.(preg_replace('/\{([^}]*)\?\}/', '{$1}', $trimmed) ?? $trimmed);
    }

    /**
     * spec 上のパステンプレートから Operation を直接引く。
     *
     * @param  string  $template  spec 上のパステンプレート
     * @param  string  $method  HTTP メソッド（大文字小文字は問わない）
     * @return ResolvedOperation|null 対応する Operation。定義がなければ null
     * @throws SpecException OpenAPI ドキュメントの読み込みに失敗した場合
     */
    public function locate(string $template, string $method): ?ResolvedOperation
    {
        $pathItem = $this->spec->load()->paths->getPath($template);

        if ($pathItem === null) {
            return null;
        }

        $method = strtolower($method);
        $operations = $pathItem->getOperations();

        return isset($operations[$method])
            ? new ResolvedOperation($template, $method, $operations[$method], $pathItem)
            : null;
    }

    /**
     * パスを比較可能な形へ正規化する。
     *
     * 変数名の違いを無視するため、波括弧の中身を捨てて位置だけを残す。
     * 例: "/members/{memberId}" と "members/{member}" はいずれも "/members/{}" になる。
     *
     * @param  string  $path  正規化対象のパス
     * @return string 正規化されたパス
     */
    private function normalize(string $path): string
    {
        $trimmed = trim($path, '/');

        if ($trimmed === '') {
            return '/';
        }

        return '/'.(preg_replace('/\{[^}]*\}/', '{}', $trimmed) ?? $trimmed);
    }

    /**
     * ルート URI から設定された接頭辞を取り除く。
     *
     * spec 側が servers でベースパスを表現し、paths には含めない場合に使う。
     *
     * @param  string  $uri  Laravel のルート URI
     * @return string 接頭辞を除いた URI
     */
    private function stripBasePath(string $uri): string
    {
        $base = trim($this->basePath, '/');
        $uri = ltrim($uri, '/');

        if ($base === '') {
            return $uri;
        }

        if ($uri === $base) {
            return '/';
        }

        if (str_starts_with($uri, $base.'/')) {
            return substr($uri, strlen($base) + 1);
        }

        return $uri;
    }
}
