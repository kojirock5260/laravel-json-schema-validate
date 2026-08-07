<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Spec;

use cebe\openapi\spec\Operation;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Response as SpecResponse;
use cebe\openapi\spec\Responses;

/**
 * Laravel のルートに対応づいた OpenAPI の Operation を表す値オブジェクト。
 */
final readonly class ResolvedOperation
{
    /**
     * @param  string  $path  spec 上のパステンプレート（例: "/members/{memberId}"）
     * @param  string  $method  小文字の HTTP メソッド（例: "get"）
     * @param  Operation  $operation  対応する Operation
     * @param  PathItem  $pathItem  Operation が属する PathItem
     * @param  array<string, string>  $parameterMap  spec 側のパラメータ名から Laravel 側の名前への対応表
     */
    public function __construct(
        public string $path,
        public string $method,
        public Operation $operation,
        public PathItem $pathItem,
        public array $parameterMap = [],
    ) {}

    /**
     * spec 上のパラメータ名に対応する Laravel 側の名前を返す。
     *
     * @param  string  $specName  spec 上のパラメータ名
     * @return string 対応する Laravel 側の名前。対応がなければ spec 側の名前
     */
    public function routeParameterName(string $specName): string
    {
        return $this->parameterMap[$specName] ?? $specName;
    }

    /**
     * メソッドとパスを連結した識別子を返す。
     *
     * @return string 例: "GET /members/{memberId}"
     */
    public function describe(): string
    {
        return strtoupper($this->method).' '.$this->path;
    }

    /**
     * ステータスコードに対応する spec 上のレスポンスを返す。
     *
     * 完全一致、2XX のような範囲指定、default の順に探す。
     *
     * @param  int  $status  ステータスコード
     * @return SpecResponse|null 対応するレスポンス。見つからなければ null
     */
    public function responseFor(int $status): ?SpecResponse
    {
        $responses = $this->operation->responses;

        if (! $responses instanceof Responses) {
            return null;
        }

        $statusText = (string) $status;

        foreach ([$statusText, $statusText[0].'XX', 'default'] as $candidate) {
            if (! $responses->hasResponse($candidate)) {
                continue;
            }

            $found = $responses->getResponse($candidate);

            if ($found instanceof SpecResponse) {
                return $found;
            }
        }

        return null;
    }
}
