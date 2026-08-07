<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kojirock5260\JsonSchemaValidate\Exception\RequestValidationException;
use Kojirock5260\JsonSchemaValidate\Exception\ResponseValidationException;
use Kojirock5260\JsonSchemaValidate\Observation\ObservationRecorder;
use Kojirock5260\JsonSchemaValidate\Spec\OperationResolver;
use Kojirock5260\JsonSchemaValidate\Validation\RequestValidator;
use Kojirock5260\JsonSchemaValidate\Validation\ResponseValidator;
use Symfony\Component\HttpFoundation\Response;

/**
 * リクエストとレスポンスを OpenAPI の定義と突き合わせるミドルウェア。
 *
 * spec に定義のないルートは検証せず素通りさせるため、
 * 一部だけ spec 化した状態でも適用できる。
 */
final readonly class ValidateOpenApi
{
    /**
     * リクエスト検証を省略する指定。
     */
    public const string SKIP_REQUEST = 'skip-request';

    /**
     * レスポンス検証を省略する指定。
     */
    public const string SKIP_RESPONSE = 'skip-response';

    /**
     * @param  OperationResolver  $resolver  ルートと Operation の対応づけ
     * @param  RequestValidator  $requestValidator  リクエストの検証器
     * @param  ResponseValidator  $responseValidator  レスポンスの検証器
     * @param  ObservationRecorder|null  $recorder  通過した往復の記録先。記録しない場合は null
     */
    public function __construct(
        private OperationResolver $resolver,
        private RequestValidator $requestValidator = new RequestValidator,
        private ResponseValidator $responseValidator = new ResponseValidator,
        private ?ObservationRecorder $recorder = null,
    ) {}

    /**
     * リクエストを検証し、後続処理のレスポンスも検証する。
     *
     * @param  Request  $request  受信したリクエスト
     * @param  Closure  $next  後続の処理
     * @param  string  ...$options  skip-request や skip-response の指定
     * @return mixed 後続処理が返したレスポンス
     * @throws RequestValidationException リクエストが定義に適合しない場合
     * @throws ResponseValidationException レスポンスが定義に適合しない場合
     */
    public function handle(Request $request, Closure $next, string ...$options): mixed
    {
        $route = $request->route();
        $operation = $this->resolver->resolve($route, $request->getMethod());

        if ($operation !== null && ! in_array(self::SKIP_REQUEST, $options, true)) {
            $errors = $this->requestValidator->validate($operation, $request);

            if ($errors !== []) {
                throw RequestValidationException::withMessages($errors);
            }
        }

        $response = $next($request);

        if (! $response instanceof Response) {
            return $response;
        }

        $this->recorder?->record(
            $operation->path ?? $this->resolver->templateFor($route),
            $request,
            $response,
        );

        if ($operation !== null && ! in_array(self::SKIP_RESPONSE, $options, true)) {
            $errors = $this->responseValidator->validate($operation, $response);

            if ($errors !== []) {
                throw ResponseValidationException::forOperation($operation->describe(), $errors);
            }
        }

        return $response;
    }
}
