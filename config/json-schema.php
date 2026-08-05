<?php

declare(strict_types=1);

return [
    // OpenAPI ドキュメントのパス。.json / .yaml / .yml に対応する
    'path' => env('OPENAPI_PATH', base_path('openapi.yaml')),

    // spec の paths に含まれない接頭辞をルート URI から取り除く（例: "api"）
    'base_path' => env('OPENAPI_BASE_PATH', ''),

    // パース結果を書き出すキャッシュファイルのパス。null ならキャッシュしない
    'cache' => env('OPENAPI_CACHE', base_path('bootstrap/cache/openapi.cache')),

    // 通過したリクエストとレスポンスの形を記録するか
    'observe' => env('OPENAPI_OBSERVE', false),

    // 記録した形を書き出すファイルのパス
    'observations' => env('OPENAPI_OBSERVATIONS', base_path('bootstrap/cache/openapi-observations.jsonl')),
];
