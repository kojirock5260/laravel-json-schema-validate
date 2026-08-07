# Laravel JsonSchema Validate

Laravel のリクエストとレスポンスを OpenAPI 3.1 のドキュメントと突き合わせて検証します。
Schema Object は変換せずに [opis/json-schema](https://opis.io/json-schema/2.x/) へ渡し、
JSON Schema 2020-12 として検証します。

[English](README.md) | 日本語

> v4 は v3 から [Claude Code](https://claude.com/claude-code) を利用して書き直しました。

## 動作環境

| | |
|---|---|
| PHP | 8.3 以上 |
| Laravel | 12.0 以上 |
| OpenAPI | 3.1 |

## インストール

```bash
composer require kojirock5260/laravel-json-schema-validate
```

```bash
php artisan vendor:publish --provider="Kojirock5260\JsonSchemaValidate\JsonSchemaServiceProvider" --tag=config
```

publish は任意です。既定値は自動で統合されます。

## 設定

`config/json-schema.php`

```php
return [
    // OpenAPI ドキュメントのパス。.json / .yaml / .yml に対応する
    'path' => env('OPENAPI_PATH', base_path('openapi.yaml')),

    // spec の paths に含まれない接頭辞をルート URI から取り除く
    'base_path' => env('OPENAPI_BASE_PATH', ''),

    // パース結果を書き出すキャッシュファイルのパス。null ならキャッシュしない
    'cache' => env('OPENAPI_CACHE', base_path('bootstrap/cache/openapi.cache')),

    // 通過したリクエストとレスポンスの形を記録するか
    'observe' => env('OPENAPI_OBSERVE', false),

    // 記録した形を書き出すファイルのパス
    'observations' => env('OPENAPI_OBSERVATIONS', base_path('bootstrap/cache/openapi-observations.jsonl')),
];
```

ルートが `/api` 配下にあり、ドキュメント側が `/members` と記述している場合は
`base_path` を指定します。

```php
'base_path' => 'api',
```

## 使い方

ミドルウェアを登録します。

```php
// bootstrap/app.php
use Kojirock5260\JsonSchemaValidate\Middleware\ValidateOpenApi;

->withMiddleware(function (Middleware $middleware) {
    $middleware->alias(['openapi' => ValidateOpenApi::class]);
})
```

ルートに適用します。

```php
Route::middleware('openapi')->group(function () {
    Route::get('/members', [MemberController::class, 'index']);
    Route::get('/members/{member}', [MemberController::class, 'show']);
    Route::post('/members', [MemberController::class, 'store']);
});
```

## キャッシュ

ドキュメントはプロセスごとに初回利用時にパースされます。
`openapi:cache` はパース結果をファイルへ書き出し、
以降のプロセスはパースせずにそこから復元します。

```bash
php artisan openapi:cache
php artisan openapi:clear
```

キャッシュ書き出し時に記録した更新時刻とドキュメントの更新時刻が異なる場合、
キャッシュは使われません。開発中にドキュメントを編集しても削除は不要です。

復元はオブジェクトグラフを一度に展開するため、パースよりピークメモリが増えます。
200 パス・303 KB の YAML ドキュメントで計測した結果です。

| | 時間 | ピークメモリ |
|---|---|---|
| キャッシュなし | 54.0 ms | 7.6 MB |
| キャッシュあり | 7.0 ms | 13.4 MB |

## 実トラフィックから組み立てる

`observe` を有効にすると、ミドルウェアを通過したトラフィックの形を記録します。
対象はクエリパラメータと、JSON のリクエストボディ・レスポンスボディです。
記録を使うコマンドが 2 つあります。

```bash
OPENAPI_OBSERVE=true php artisan test
```

### ドキュメントを作る — `openapi:generate`

観測した内容を OpenAPI ドキュメントとして書き出します。
現在のドキュメントに記載の無いルートも記録するため、ドキュメントが無い状態から使えます。

```bash
php artisan openapi:generate --output=openapi.yaml
```

```yaml
openapi: 3.1.0
info:
  title: 'Generated from observed traffic'
  version: 1.0.0
paths:
  /widgets/{widget}:
    get:
      parameters:
        - { name: widget, in: path, required: true, schema: { type: string } }
        - { name: page, in: query, required: false, schema: { type: integer } }
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                required: [id, label]
                properties:
                  id: { type: integer }
                  label: { type: string }
```

| 指定 | 効果 |
|---|---|
| `--output=` | 標準出力ではなくこのファイルへ書き出す |
| `--format=` | `yaml`（既定）または `json` |
| `--title=` | `info.title` に入れる値 |
| `--api-version=` | `info.version` に入れる値 |

### ドキュメントと突き合わせる — `openapi:diff`

記録と現在のドキュメントを比べ、食い違いを一覧します。

```bash
php artisan openapi:diff
```

```
GET /members
  response 200
    body.data[].joinedAt  Observed but not described in the specification.
    body.data[].name      Observed type integer but the specification allows string.
    body.data[]           The specification requires name but they were not always present.

 WARN  Found 3 difference(s) between the recorded traffic and the specification.
```

報告する内容です。

- ドキュメントに記載のない項目が観測された
- ドキュメントが許していない型が観測された
- ドキュメントが `required` としている項目が、観測の一部で欠けていた
- ドキュメントに定義のない Operation が観測された

ドキュメントに記載があるのに観測されなかった項目は報告しません。
そのコードパスを通っていないだけの可能性があるためです。

| 指定 | 効果 |
|---|---|
| `--strict` | 差分があれば異常終了する |
| `--forget` | 報告後に記録を削除する |

### 記録のまとめ方

型は和を、`required` は積を取ります。
一度でも欠けたキーは `required` から外れ、複数の型で観測された項目は union になります。
観測した順序によって結果は変わりません。

これは**観測できた範囲を表したもので、真実とは限りません。**
`required: false` には根拠があります（その項目が無くてもリクエストが成立した実例があります）。
`required: true` は反例が見つからなかったという意味でしかありません。
一度も通らなかった経路は、そもそも現れません。
生成したドキュメントは、そのまま使うものではなく**見直しの出発点**です。

記録するのは型と構造だけで、値は観測ファイルに一切書き出しません。
リクエストボディはボディを持つメソッドのみ記録し、
`GET` / `HEAD` / `OPTIONS` / `TRACE` は対象外です。

## 検証する対象

**リクエスト**

- パス・クエリ・ヘッダ・クッキーのパラメータ（Operation と PathItem の双方の定義）
- `required` の指定
- リクエストボディ（`Content-Type` で選択）

**レスポンス**

- ステータスコード（完全一致 → 範囲指定 `4XX` → `default` の順に解決）
- レスポンスヘッダ（`required` を含む）
- レスポンスボディ（`Content-Type` で選択）

## 対応するもの

- `.json` / `.yaml` / `.yml` の OpenAPI 3.1 ドキュメント
- JSON Schema 2020-12 のキーワード。`const`、`prefixItems`、`dependentRequired`、
  `unevaluatedProperties`、数値の `exclusiveMinimum`、`[string, 'null']` のような型の union
- ドキュメント内の `$ref`
- spec と名前が異なるルートパラメータ。ルートは `paths` と位置で突き合わせるため、
  `members/{member}` は `/members/{memberId}` に一致する。任意パラメータ（`{member?}`）も含む
- ドキュメントに記載のないルート。検証せず素通りする
- 宣言された型への変換。`type: integer` なら `?page=3` は `3` になる。
  変換できない値はそのまま残り、検証で不正と判定される
- `content` を選ぶ際のメディアタイプのワイルドカード（`application/*`、`*/*`）

## 対応しないもの

- OpenAPI 3.0 の方言。ドキュメントの読み込みはできるが、3.0 固有のキーワードは変換しない。
  `nullable: true` は無視されるため、その書き方をしたフィールドは `null` を弾く。
  `type: [string, 'null']` と記述する。boolean の `exclusiveMinimum` も 3.0 の定義通りには解釈しない
- JSON 以外のメディアタイプのボディの中身。メディアタイプの突き合わせは行うが、
  中身を検証するのは `application/json` と `+json` のサブタイプのみ
- `schema` ではなく `content` で宣言されたパラメータ

## エラーの扱い

リクエストの検証失敗は `RequestValidationException` を送出します。
Laravel の `ValidationException` を継承しているため、追加の設定なしに 422 として描画されます。

```json
{
  "message": "Number must be greater than 0 (and 1 more error)",
  "errors": {
    "page": ["Number must be greater than 0"],
    "status": ["The data must match the const value"]
  }
}
```

キーはドット記法です。ボディ全体に対するエラーは `body` にまとまります。

レスポンスの検証失敗は `ResponseValidationException` を送出します。
これは素の `RuntimeException` なので、422 ではなく 500 になります。

```php
use Kojirock5260\JsonSchemaValidate\Exception\ResponseValidationException;

try {
    // ...
} catch (ResponseValidationException $e) {
    $e->operation; // "GET /members"
    $e->errors;    // ['X-Total-Count' => ['The X-Total-Count response header is required.']]
}
```

## ルート単位の指定

```php
Route::middleware('openapi:skip-response')->get('/members', $handler);
Route::middleware('openapi:skip-request')->get('/members', $handler);
```

```php
Route::middleware(app()->isProduction() ? 'openapi:skip-response' : 'openapi')->group(...);
```

## v3 からの移行

v4 は v3 と API を共有しません。

| | v3 | v4 |
|---|---|---|
| スキーマの置き場所 | `App\Http\Schema` 配下の PHP クラス | OpenAPI ドキュメント |
| 解決方法 | ルート名 = クラス名 | パスとメソッド |
| 検証エンジン | justinrainbow/json-schema | opis/json-schema |
| 準拠仕様 | draft-04 相当 | JSON Schema 2020-12 |
| エラー処理 | `prepareException` の手動設定 | 自動で 422 |

`SchemaInterface`、`JsonSchemaValidator`、`JsonSchemaException` は削除しました。
スキーマクラスを OpenAPI ドキュメントとして書き直し、`ValidateOpenApi` を登録してください。

## 開発

```bash
composer install
composer check   # pint --test, phpstan, pest
```

## ライセンス

MIT License。詳細は [License File](LICENSE) を参照してください。
