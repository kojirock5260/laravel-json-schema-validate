# Laravel JsonSchema Validate

Validates Laravel requests and responses against an OpenAPI 3.1 document.
Schema Objects are passed to [opis/json-schema](https://opis.io/json-schema/2.x/) unchanged and
validated as JSON Schema 2020-12.

English | [日本語](README.ja.md)

> v4 was rewritten from v3 with [Claude Code](https://claude.com/claude-code).

## Requirements

| | |
|---|---|
| PHP | 8.3+ |
| Laravel | 12.0+ |
| OpenAPI | 3.1 |

## Installation

```bash
composer require kojirock5260/laravel-json-schema-validate
```

```bash
php artisan vendor:publish --provider="Kojirock5260\JsonSchemaValidate\JsonSchemaServiceProvider" --tag=config
```

Publishing is optional. The defaults are merged automatically.

## Configuration

`config/json-schema.php`

```php
return [
    // Path to the OpenAPI document. .json / .yaml / .yml are supported.
    'path' => env('OPENAPI_PATH', base_path('openapi.yaml')),

    // Prefix to strip from route URIs when the spec does not include it in `paths`.
    'base_path' => env('OPENAPI_BASE_PATH', ''),

    // Where the parsed document is cached. Set to null to disable caching.
    'cache' => env('OPENAPI_CACHE', base_path('bootstrap/cache/openapi.cache')),

    // Whether to record the shape of the traffic that passes through the middleware.
    'observe' => env('OPENAPI_OBSERVE', false),

    // Where the recorded shapes are written.
    'observations' => env('OPENAPI_OBSERVATIONS', base_path('bootstrap/cache/openapi-observations.jsonl')),
];
```

Use `base_path` when routes live under `/api` but the document describes them as `/members`:

```php
'base_path' => 'api',
```

## Usage

Register the middleware:

```php
// bootstrap/app.php
use Kojirock5260\JsonSchemaValidate\Middleware\ValidateOpenApi;

->withMiddleware(function (Middleware $middleware) {
    $middleware->alias(['openapi' => ValidateOpenApi::class]);
})
```

Apply it to routes:

```php
Route::middleware('openapi')->group(function () {
    Route::get('/members', [MemberController::class, 'index']);
    Route::get('/members/{member}', [MemberController::class, 'show']);
    Route::post('/members', [MemberController::class, 'store']);
});
```

## Caching

The document is parsed on first use in each process. `openapi:cache` writes the parsed document to a
file so that later processes restore it instead of parsing it again.

```bash
php artisan openapi:cache
php artisan openapi:clear
```

The cache is ignored when the modification time of the document differs from the one recorded when the
cache was written, so editing the document during development does not require clearing it.

Restoring uses more peak memory than parsing, because the whole object graph is materialised at once.
Measured with a 303 KB YAML document describing 200 paths:

| | Time | Peak memory |
|---|---|---|
| Without cache | 54.0 ms | 7.6 MB |
| With cache | 7.0 ms | 13.4 MB |

## Working from observed traffic

With `observe` enabled, the middleware records the shape of the traffic that passes through it: query
parameters, and JSON request and response bodies. Two commands consume those recordings.

```bash
OPENAPI_OBSERVE=true php artisan test
```

### Building a document — `openapi:generate`

Writes an OpenAPI document describing what was observed. Routes the current document does not describe
are recorded too, so this works with no document at all.

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

| Option | Effect |
|---|---|
| `--output=` | Write to this file instead of standard output |
| `--format=` | `yaml` (default) or `json` |
| `--title=` | Value for `info.title` |
| `--api-version=` | Value for `info.version` |

### Comparing with a document — `openapi:diff`

Compares the recordings with the current document and lists the differences.

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

Reported:

- a field observed that the document does not describe
- a type observed that the document does not allow
- a field the document marks `required` that was absent from some recordings
- an operation the document does not define

Fields the document describes but that were never observed are not reported, since that only means the
code path was not exercised.

| Option | Effect |
|---|---|
| `--strict` | Exit with a failure code when differences are found |
| `--forget` | Remove the recordings after reporting |

### How recordings are merged

Types are unioned and `required` is intersected. A key absent from any single recording is dropped from
`required`; a field seen with more than one type becomes a union. The result does not depend on the
order in which the traffic was observed.

This describes what was observed, not what is true. `required: false` is backed by evidence — a request
succeeded without that field. `required: true` only means no counter-example was seen. Paths that were
never exercised do not appear at all. A generated document is a starting point to review, not a
finished specification.

Only types and structure are recorded. No values are written to the observations file. Request bodies
are recorded for methods that carry one; `GET`, `HEAD`, `OPTIONS` and `TRACE` are skipped.

## What is validated

**Request**

- Path, query, header and cookie parameters, declared on the operation or on the path item
- `required` parameters
- Request body, selected by `Content-Type`

**Response**

- Status code, resolved as exact match, then range (`4XX`), then `default`
- Response headers, including `required`
- Response body, selected by `Content-Type`

## Supported

- OpenAPI 3.1 documents in `.json`, `.yaml` and `.yml`
- JSON Schema 2020-12 keywords, including `const`, `prefixItems`, `dependentRequired`,
  `unevaluatedProperties`, numeric `exclusiveMinimum` and type unions such as `[string, 'null']`
- `$ref` within the document
- Route parameters whose names differ from the spec. Routes are matched to `paths` by position, so
  `members/{member}` matches `/members/{memberId}`, including optional parameters (`{member?}`)
- Routes absent from the document. They pass through without validation
- String parameters converted to the declared type before validation. `?page=3` becomes `3` for
  `type: integer`. A value that cannot be converted is left as-is and fails validation
- Media type wildcards (`application/*`, `*/*`) when selecting a `content` entry

## Not supported

- The OpenAPI 3.0 dialect. Documents are parsed, but 3.0-only keywords are not translated:
  `nullable: true` is ignored, so a field written that way rejects `null`. Use `type: [string, 'null']`.
  Boolean `exclusiveMinimum` is not interpreted as 3.0 defines it
- Body contents of non-JSON media types. The media type is matched, but only `application/json` and
  `+json` subtypes have their contents validated
- Parameters declared with `content` instead of `schema`

## Error handling

Request failures throw `RequestValidationException`, which extends Laravel's `ValidationException`.
Laravel renders it as a 422 without further configuration:

```json
{
  "message": "Number must be greater than 0 (and 1 more error)",
  "errors": {
    "page": ["Number must be greater than 0"],
    "status": ["The data must match the const value"]
  }
}
```

Keys use dot notation. Errors on the body as a whole are reported under `body`.

Response failures throw `ResponseValidationException`, a plain `RuntimeException`, which results in a
500 rather than a 422.

```php
use Kojirock5260\JsonSchemaValidate\Exception\ResponseValidationException;

try {
    // ...
} catch (ResponseValidationException $e) {
    $e->operation; // "GET /members"
    $e->errors;    // ['X-Total-Count' => ['The X-Total-Count response header is required.']]
}
```

## Per-route options

```php
Route::middleware('openapi:skip-response')->get('/members', $handler);
Route::middleware('openapi:skip-request')->get('/members', $handler);
```

```php
Route::middleware(app()->isProduction() ? 'openapi:skip-response' : 'openapi')->group(...);
```

## Upgrading from v3

v4 shares no API with v3.

| | v3 | v4 |
|---|---|---|
| Schema source | PHP classes under `App\Http\Schema` | OpenAPI document |
| Resolution | Route name equals class name | Path and method |
| Validator | justinrainbow/json-schema | opis/json-schema |
| Dialect | draft-04 era | JSON Schema 2020-12 |
| Error handling | Manual `prepareException` wiring | Automatic 422 |

`SchemaInterface`, `JsonSchemaValidator` and `JsonSchemaException` have been removed. Rewrite the
schema classes as an OpenAPI document and register `ValidateOpenApi`.

## Development

```bash
composer install
composer check   # pint --test, phpstan, pest
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
