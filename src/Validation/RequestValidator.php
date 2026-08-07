<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Validation;

use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\RequestBody;
use Illuminate\Http\Request;
use JsonException;
use Kojirock5260\JsonSchemaValidate\Spec\ResolvedOperation;
use Kojirock5260\JsonSchemaValidate\Spec\SpecSchema;

/**
 * リクエストを Operation の定義と突き合わせて検証する。
 *
 * パラメータとリクエストボディの双方を対象とする。
 */
final readonly class RequestValidator
{
    /**
     * ボディ全体に対するエラーを載せるキー。
     */
    private const string BODY_KEY = 'body';

    /**
     * @param  SchemaValidator  $validator  JSON Schema による検証器
     */
    public function __construct(
        private SchemaValidator $validator = new SchemaValidator,
    ) {}

    /**
     * リクエストを検証し、エラー一覧を返す。
     *
     * @param  ResolvedOperation  $operation  対応づいた Operation
     * @param  Request  $request  検証対象のリクエスト
     * @return array<string, list<string>> ドット記法をキーにしたエラー一覧。妥当なら空配列
     */
    public function validate(ResolvedOperation $operation, Request $request): array
    {
        return ErrorKey::merge(
            $this->validateParameters($operation, $request),
            $this->validateBody($operation, $request),
        );
    }

    /**
     * パラメータをまとめて検証する。
     *
     * @param  ResolvedOperation  $operation  対応づいた Operation
     * @param  Request  $request  検証対象のリクエスト
     * @return array<string, list<string>> エラー一覧
     */
    private function validateParameters(ResolvedOperation $operation, Request $request): array
    {
        $errors = [];

        foreach ($this->parameters($operation) as $parameter) {
            $errors = ErrorKey::merge($errors, $this->validateParameter($parameter, $operation, $request));
        }

        return $errors;
    }

    /**
     * PathItem と Operation のパラメータを統合する。
     *
     * 同じ名前と位置の組み合わせは Operation 側で上書きする。
     *
     * @param  ResolvedOperation  $operation  対応づいた Operation
     * @return list<Parameter> 統合されたパラメータの一覧
     */
    private function parameters(ResolvedOperation $operation): array
    {
        $merged = [];

        foreach ([$operation->pathItem->parameters, $operation->operation->parameters] as $group) {
            foreach ($group as $parameter) {
                if ($parameter instanceof Parameter) {
                    $merged[$parameter->in.':'.$parameter->name] = $parameter;
                }
            }
        }

        return array_values($merged);
    }

    /**
     * パラメータを1件検証する。
     *
     * @param  Parameter  $parameter  検証対象のパラメータ定義
     * @param  ResolvedOperation  $operation  対応づいた Operation
     * @param  Request  $request  検証対象のリクエスト
     * @return array<string, list<string>> エラー一覧
     */
    private function validateParameter(Parameter $parameter, ResolvedOperation $operation, Request $request): array
    {
        $name = $parameter->name;
        $value = $this->parameterValue($parameter, $operation, $request);

        if ($value === null) {
            return $parameter->required
                ? [$name => ["The {$name} {$parameter->in} parameter is required."]]
                : [];
        }

        $schema = SpecSchema::toJsonSchema($parameter->schema);

        if ($schema === null) {
            return [];
        }

        $coerced = ParameterCoercer::coerce($value, $schema);

        return ErrorKey::rekey($this->validator->errors(JsonValue::normalize($coerced), $schema), $name, $name);
    }

    /**
     * パラメータの位置に応じてリクエストから値を取り出す。
     *
     * @param  Parameter  $parameter  検証対象のパラメータ定義
     * @param  ResolvedOperation  $operation  対応づいた Operation
     * @param  Request  $request  検証対象のリクエスト
     * @return mixed 取り出した値。存在しなければ null
     */
    private function parameterValue(Parameter $parameter, ResolvedOperation $operation, Request $request): mixed
    {
        $name = $parameter->name;

        return match ($parameter->in) {
            'path' => $this->pathParameterValue($operation, $request, $name),
            'query' => $request->query->has($name) ? $request->query->all()[$name] : null,
            'header' => $request->headers->get($name),
            'cookie' => $request->cookies->get($name),
            default => null,
        };
    }

    /**
     * パスパラメータの値を、ルート側の名前に読み替えて取り出す。
     *
     * @param  ResolvedOperation  $operation  対応づいた Operation
     * @param  Request  $request  検証対象のリクエスト
     * @param  string  $name  spec 上のパラメータ名
     * @return mixed 取り出した値。存在しなければ null
     */
    private function pathParameterValue(ResolvedOperation $operation, Request $request, string $name): mixed
    {
        return $request->route()->parameter($operation->routeParameterName($name));
    }

    /**
     * リクエストボディを検証する。
     *
     * @param  ResolvedOperation  $operation  対応づいた Operation
     * @param  Request  $request  検証対象のリクエスト
     * @return array<string, list<string>> エラー一覧
     */
    private function validateBody(ResolvedOperation $operation, Request $request): array
    {
        $requestBody = $operation->operation->requestBody;

        if (! $requestBody instanceof RequestBody) {
            return [];
        }

        $content = $request->getContent();

        if ($content === '') {
            return $requestBody->required
                ? [self::BODY_KEY => ['The request body is required.']]
                : [];
        }

        $contentType = $request->headers->get('Content-Type');
        $mediaType = MediaTypeMatcher::match(array_keys($requestBody->content), $contentType);

        if ($mediaType === null) {
            return [self::BODY_KEY => ["The content type [{$contentType}] is not defined for this operation."]];
        }

        if (! MediaTypeMatcher::isJson($mediaType)) {
            return [];
        }

        $schema = SpecSchema::toJsonSchema($requestBody->content[$mediaType]->schema);

        if ($schema === null) {
            return [];
        }

        try {
            $decoded = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return [self::BODY_KEY => ['The request body is not valid JSON: '.$exception->getMessage()]];
        }

        return ErrorKey::rekey($this->validator->errors($decoded, $schema), '', self::BODY_KEY);
    }
}
