<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Validation;

use cebe\openapi\spec\Header;
use cebe\openapi\spec\Response as SpecResponse;
use JsonException;
use Kojirock5260\JsonSchemaValidate\Spec\ResolvedOperation;
use Kojirock5260\JsonSchemaValidate\Spec\SpecSchema;
use Symfony\Component\HttpFoundation\Response;

/**
 * レスポンスを Operation の定義と突き合わせて検証する。
 *
 * ステータスコード・ヘッダ・ボディを対象とする。
 */
final readonly class ResponseValidator
{
    /**
     * ボディ全体に対するエラーを載せるキー。
     */
    private const string BODY_KEY = 'body';

    /**
     * ステータスコードに対するエラーを載せるキー。
     */
    private const string STATUS_KEY = 'status';

    /**
     * @param  SchemaValidator  $validator  JSON Schema による検証器
     */
    public function __construct(
        private SchemaValidator $validator = new SchemaValidator,
    ) {}

    /**
     * レスポンスを検証し、エラー一覧を返す。
     *
     * @param  ResolvedOperation  $operation  対応づいた Operation
     * @param  Response  $response  検証対象のレスポンス
     * @return array<string, list<string>> ドット記法をキーにしたエラー一覧。妥当なら空配列
     */
    public function validate(ResolvedOperation $operation, Response $response): array
    {
        $status = $response->getStatusCode();
        $specResponse = $operation->responseFor($status);

        if (! $specResponse instanceof SpecResponse) {
            return [self::STATUS_KEY => ["The status code [{$status}] is not defined for this operation."]];
        }

        return ErrorKey::merge(
            $this->validateHeaders($specResponse, $response),
            $this->validateBody($specResponse, $response),
        );
    }

    /**
     * レスポンスヘッダを検証する。
     *
     * @param  SpecResponse  $specResponse  spec 上のレスポンス定義
     * @param  Response  $response  検証対象のレスポンス
     * @return array<string, list<string>> エラー一覧
     */
    private function validateHeaders(SpecResponse $specResponse, Response $response): array
    {
        $errors = [];

        foreach ($specResponse->headers as $name => $header) {
            if (! $header instanceof Header) {
                continue;
            }

            $errors = ErrorKey::merge($errors, $this->validateHeader((string) $name, $header, $response));
        }

        return $errors;
    }

    /**
     * レスポンスヘッダを1件検証する。
     *
     * @param  string  $name  ヘッダ名
     * @param  Header  $header  spec 上のヘッダ定義
     * @param  Response  $response  検証対象のレスポンス
     * @return array<string, list<string>> エラー一覧
     */
    private function validateHeader(string $name, Header $header, Response $response): array
    {
        $value = $response->headers->get($name);

        if ($value === null) {
            return $header->required
                ? [$name => ["The {$name} response header is required."]]
                : [];
        }

        $schema = SpecSchema::toJsonSchema($header->schema);

        if ($schema === null) {
            return [];
        }

        $coerced = ParameterCoercer::coerce($value, $schema);

        return ErrorKey::rekey($this->validator->errors(JsonValue::normalize($coerced), $schema), $name, $name);
    }

    /**
     * レスポンスボディを検証する。
     *
     * @param  SpecResponse  $specResponse  spec 上のレスポンス定義
     * @param  Response  $response  検証対象のレスポンス
     * @return array<string, list<string>> エラー一覧
     */
    private function validateBody(SpecResponse $specResponse, Response $response): array
    {
        if ($specResponse->content === []) {
            return [];
        }

        $contentType = $response->headers->get('Content-Type');
        $mediaType = MediaTypeMatcher::match(array_keys($specResponse->content), $contentType);

        if ($mediaType === null) {
            return [self::BODY_KEY => ["The content type [{$contentType}] is not defined for this response."]];
        }

        if (! MediaTypeMatcher::isJson($mediaType)) {
            return [];
        }

        $schema = SpecSchema::toJsonSchema($specResponse->content[$mediaType]->schema);

        if ($schema === null) {
            return [];
        }

        $content = $response->getContent();

        if ($content === false || $content === '') {
            return [self::BODY_KEY => ['The response body is empty but a schema is defined.']];
        }

        try {
            $decoded = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return [self::BODY_KEY => ['The response body is not valid JSON: '.$exception->getMessage()]];
        }

        return ErrorKey::rekey($this->validator->errors($decoded, $schema), '', self::BODY_KEY);
    }
}
