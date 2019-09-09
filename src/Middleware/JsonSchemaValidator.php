<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Middleware;

use Kojirock5260\JsonSchemaValidate\SchemaInterface;

class JsonSchemaValidator
{
    const TYPE_REQUEST  = 'Request';
    const TYPE_RESPONSE = 'Response';

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     * @throws \Exception
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        $route = $this->getRoute();

        if (strlen((string) $route->getName()) === 0) {
            return $next($request);
        }

        $validator = new \JsonSchema\Validator();

        $this->validateRequest($validator, $request, $route);

        $response = $next($request);

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $this->validateResponse($validator, $response, $route);
        }

        return $response;
    }

    /**
     * Get JsonSchema ClassName.
     * @param \Illuminate\Routing\Route $route
     * @param string                    $type
     * @return string
     */
    public function getJsonSchemaClassName(\Illuminate\Routing\Route $route, string $type): string
    {
        $routeName = $route->getName();
        return "App\\Http\\Schema\\{$type}\\{$routeName}Schema";
    }

    /**
     * Get Schema
     * @param \Illuminate\Routing\Route $route
     * @param string                    $type
     * @return null|array
     */
    protected function getSchema(\Illuminate\Routing\Route $route, string $type): ?array
    {
        $className = $this->getJsonSchemaClassName($route, $type);

        if (class_exists($className) && isset(class_implements($className)[SchemaInterface::class])) {
            return $className::getSchema();
        }

        return null;
    }

    /**
     * Get Route.
     * @return \Illuminate\Routing\Route
     */
    protected function getRoute(): \Illuminate\Routing\Route
    {
        return \Illuminate\Support\Facades\Route::getCurrentRoute();
    }

    /**
     * Request Validate.
     * @param \JsonSchema\Validator     $v
     * @param \Illuminate\Http\Request  $request
     * @param \Illuminate\Routing\Route $route
     * @throws \Exception
     */
    protected function validateRequest(\JsonSchema\Validator $v, \Illuminate\Http\Request $request, \Illuminate\Routing\Route $route): void
    {
        $requestSchema = $this->getSchema($route, self::TYPE_REQUEST);

        if ($requestSchema !== null) {
            $v->check((object) $request->all(), $requestSchema);

            if ($v->numErrors() >= 1) {
                $exceptionClass = \Illuminate\Support\Facades\Config::get('json-schema.exception');
                throw new $exceptionClass(serialize($v->getErrors()));
            }
        }
    }

    /**
     * Response Validate.
     * @param \JsonSchema\Validator         $v
     * @param \Illuminate\Http\JsonResponse $response
     * @param \Illuminate\Routing\Route     $route
     * @throws \Exception
     */
    protected function validateResponse(\JsonSchema\Validator $v, \Illuminate\Http\JsonResponse $response, \Illuminate\Routing\Route $route): void
    {
        $responseSchema = $this->getSchema($route, self::TYPE_RESPONSE);

        if ($responseSchema !== null) {
            if ($response->isSuccessful()) {
                $v->check($response->getData(), $responseSchema);

                if ($v->numErrors() >= 1) {
                    $exceptionClass = \Illuminate\Support\Facades\Config::get('json-schema.exception');
                    throw new $exceptionClass(serialize($v->getErrors()));
                }
            }
        }
    }
}
