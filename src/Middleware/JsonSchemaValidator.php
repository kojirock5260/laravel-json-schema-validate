<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Middleware;

use Closure;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Config;
use JsonSchema\Validator;
use Kojirock5260\JsonSchemaValidate\SchemaInterface;

class JsonSchemaValidator
{
    private const TYPE_REQUEST = 'Request';
    private const TYPE_RESPONSE = 'Response';

    /**
     * Handle an incoming request.
     * @param Request $request
     * @param Closure $next
     * @return mixed
     * @throws Exception
     */
    public function handle(Request $request, Closure $next)
    {
        $route = $this->getRoute();
        if ('' === (string) $route->getName()) {
            return $next($request);
        }

        $validator = new Validator();
        $this->validateRequest($validator, $request, $route);
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $this->validateResponse($validator, $response, $route);
        }

        return $response;
    }

    /**
     * Get JsonSchema ClassName.
     * @param Route $route
     * @param string $type
     * @return string
     */
    protected function getJsonSchemaClassName(Route $route, string $type): string
    {
        $routeName = $route->getName();
        return "App\\Http\\Schema\\{$type}\\{$routeName}Schema";
    }

    /**
     * Get Schema.
     * @param Route $route
     * @param string $type
     * @return array|null
     */
    private function getSchema(Route $route, string $type): ?array
    {
        $className = $this->getJsonSchemaClassName($route, $type);
        if (class_exists($className) && isset(class_implements($className)[SchemaInterface::class])) {
            return $className::getSchema();
        }

        return null;
    }

    /**
     * Get Route.
     * @return Route
     */
    private function getRoute(): Route
    {
        return \Illuminate\Support\Facades\Route::current();
    }

    /**
     * Request Validate.
     * @param Validator $v
     * @param Request $request
     * @param Route $route
     * @throws Exception
     */
    private function validateRequest(Validator $v, Request $request, Route $route): void
    {
        $requestSchema = $this->getSchema($route, self::TYPE_REQUEST);
        if (null !== $requestSchema) {
            $v->check((object) $request->all(), $requestSchema);
            if ($v->numErrors() >= 1) {
                $exceptionClass = Config::get('json-schema.exception');
                throw new $exceptionClass(serialize($v->getErrors()));
            }
        }
    }

    /**
     * Response Validate.
     * @param Validator $v
     * @param JsonResponse $response
     * @param Route $route
     * @throws Exception
     */
    private function validateResponse(Validator $v, JsonResponse $response, Route $route): void
    {
        $responseSchema = $this->getSchema($route, self::TYPE_RESPONSE);
        if (null !== $responseSchema && $response->isSuccessful()) {
            $v->check($response->getData(), $responseSchema);
            if ($v->numErrors() >= 1) {
                $exceptionClass = Config::get('json-schema.exception');
                throw new $exceptionClass(serialize($v->getErrors()));
            }
        }
    }
}
