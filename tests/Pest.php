<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Kojirock5260\JsonSchemaValidate\Spec\SpecRepository;
use Kojirock5260\JsonSchemaValidate\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * fixtures 配下のファイルへの絶対パスを返す。
 *
 * @param  string  $file  fixtures 配下のファイル名
 * @return string 絶対パス
 */
function fixturePath(string $file): string
{
    return __DIR__.'/fixtures/'.$file;
}

/**
 * fixture の OpenAPI ドキュメントを指す SpecRepository を生成する。
 *
 * @param  string  $file  fixtures 配下のファイル名
 * @return SpecRepository 生成されたリポジトリ
 */
function specRepository(string $file = 'openapi.yaml'): SpecRepository
{
    return new SpecRepository(fixturePath($file));
}

/**
 * テスト用の Laravel ルートを生成する。
 *
 * @param  string  $uri  ルート URI
 * @param  string  $method  HTTP メソッド
 * @return Route 生成されたルート
 */
function testRoute(string $uri, string $method = 'GET'): Route
{
    return new Route([$method], $uri, static fn () => null);
}

/**
 * JSON 文字列をスキーマオブジェクトへ変換する。
 *
 * @param  string  $json  JSON Schema を表す JSON 文字列
 * @return object デコード済みのスキーマ
 */
function schema(string $json): object
{
    return json_decode($json, false, 512, JSON_THROW_ON_ERROR);
}

/**
 * ルートを解決済みのリクエストを生成する。
 *
 * @param  string  $routeUri  ルート定義の URI（例: "members/{member}"）
 * @param  string  $target  実際にアクセスする URI（例: "/members?page=3"）
 * @param  string  $method  HTTP メソッド
 * @param  string|null  $content  リクエストボディ
 * @param  array<string, string>  $headers  付与するヘッダ
 * @return Request 生成されたリクエスト
 */
function requestFor(
    string $routeUri,
    string $target,
    string $method = 'GET',
    ?string $content = null,
    array $headers = [],
): Request {
    $request = Request::create($target, $method, [], [], [], [], $content);

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    $route = testRoute($routeUri, $method);
    $route->bind($request);
    $request->setRouteResolver(static fn (): Route => $route);

    return $request;
}
