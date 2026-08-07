<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Observation;

use Illuminate\Http\Request;
use Kojirock5260\JsonSchemaValidate\Validation\MediaTypeMatcher;
use Symfony\Component\HttpFoundation\Response;

/**
 * 通過したリクエストとレスポンスから形を取り出して記録する。
 *
 * 対象は JSON のボディとクエリパラメータで、値は保持せず型と構造だけを残す。
 */
final readonly class ObservationRecorder
{
    /**
     * ボディに意味を持たない HTTP メソッド。
     *
     * これらのメソッドでは、クライアントが空のボディを送っても記録しない。
     *
     * @var list<string>
     */
    private const array BODYLESS_METHODS = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];

    /**
     * @param  ObservationStore  $store  観測結果の保存先
     */
    public function __construct(
        private ObservationStore $store,
    ) {}

    /**
     * 1回の往復を記録する。
     *
     * @param  string  $path  パステンプレート
     * @param  Request  $request  通過したリクエスト
     * @param  Response  $response  返されたレスポンス
     */
    public function record(string $path, Request $request, Response $response): void
    {
        $requestBody = in_array(strtoupper($request->getMethod()), self::BODYLESS_METHODS, true)
            ? null
            : $this->shapeOf($request->getContent(), $request->headers->get('Content-Type'));

        $this->store->record(new Observation(
            $path,
            strtolower($request->getMethod()),
            $response->getStatusCode(),
            $requestBody,
            $this->shapeOf($response->getContent(), $response->headers->get('Content-Type')),
            ParameterInference::fromQuery($request->query->all()),
        ));
    }

    /**
     * ボディから形を取り出す。
     *
     * @param  string|false  $content  ボディの中身
     * @param  string|null  $contentType  Content-Type ヘッダの値
     * @return array<string, mixed>|null 取り出した形。取り出せなければ null
     */
    private function shapeOf(string|false $content, ?string $contentType): ?array
    {
        if ($content === false || $content === '' || ! MediaTypeMatcher::isJson($contentType)) {
            return null;
        }

        $decoded = json_decode($content);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        $shape = ShapeInference::fromValue($decoded);

        return $shape === [] ? null : $shape;
    }
}
