<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Exception;

use Illuminate\Validation\ValidationException;

/**
 * リクエストが OpenAPI の定義に適合しなかったことを表す例外。
 *
 * ValidationException を継承しているため、Laravel の既定の扱いに沿って
 * 422 と標準のエラー形式で描画される。
 */
final class RequestValidationException extends ValidationException {}
