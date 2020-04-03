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
     * @var Validator
     */
    private $validator;

    /**
     * JsonSchemaValidator constructor.
     * @param Validator $validator
     */
    public function __construct(Validator $validator)
    {
        $this->validator = $validator;
    }

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

        $this->validateRequest($request, $route);
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $this->validateResponse($response, $route);
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
        return "App\\Http\\Schema\\{$type}\\{$routeName}";
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
     * @param Request $request
     * @param Route $route
     * @throws Exception
     */
    private function validateRequest(Request $request, Route $route): void
    {
        $requestSchema = $this->getSchema($route, self::TYPE_REQUEST);
        if (null !== $requestSchema) {
            $this->validator->check((object) $request->all(), $requestSchema);
            if ($this->validator->numErrors() >= 1) {
                $exceptionClass = Config::get('json-schema.exception');
                throw new $exceptionClass(serialize($this->validator->getErrors()));
            }
        }
    }

    /**
     * Response Validate.
     * @param JsonResponse $response
     * @param Route $route
     * @throws Exception
     */
    private function validateResponse(JsonResponse $response, Route $route): void
    {
        $responseSchema = $this->getSchema($route, self::TYPE_RESPONSE);
        if (null !== $responseSchema && $response->isSuccessful()) {
            $this->validator->check($response->getData(), $responseSchema);
            if ($this->validator->numErrors() >= 1) {
                $exceptionClass = Config::get('json-schema.exception');
                throw new $exceptionClass(serialize($this->validator->getErrors()));
            }
        }
    }
}
