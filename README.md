# Laravel JsonSchema Validate


JsonSchema Validate For Laravel Request And Response 

## Installation

```bash
composer require kojirock5260/laravel-json-schema-validate
```

```bash
php artisan vendor:publish --provider=Kojirock5260\\JsonSchemaServiceProvider
```

## Setup

Please describe

### 1. app/Http/Kernel.php

```php
    protected $routeMiddleware = [
        ...
        'json_schema' => \Kojirock5260\Middleware\JsonSchemaValidate::class,
    ];
```

### 2. routes/*.php

```php
<?php

Route::group(['middleware' => ['json_schema']], function () {
    Route::get('/member', 'MemberController@index')->name('MemberList');
});
```

* Be sure to set the route name

### 3. Schema Files

#### Path

##### Request

* App\Http\Schema\Request\\{RouteName}.php

##### Response

* App\Http\Schema\Response\\{RouteName}.php 

```php
<?php

declare(strict_types=1);

namespace App\Http\Schema\Request;

use Kojirock5260\SchemaInterface;

class MemberListSchema implements SchemaInterface
{
    public static function getSchema(): array
    {
        return [
            '$schema'    => 'http://json-schema.org/draft-07/schema#',
            'required'   => ['page'],
            'type'       => 'object',
            'properties' => [
                'page' => [
                    'type'    => 'string',
                    'pattern' => '^([1-9]|[1-9][0-9]*)$',
                ],
                'employment' => [
                    'type'    => 'string',
                    'enum'    => array_map('strval', array_keys(\App\Models\Member::EMPLOYMENT_LIST)),
                ],
                'department' => [
                    'type'    => 'string',
                    'enum'    => array_map('strval', array_keys(\App\Models\Member::DEPARTMENT_LIST)),
                ],
                'mailAddress' => [
                    'type' => 'string',
                    'format' => 'email'
                ],
            ],
        ];
    }
}

```


## Schema Directory Customise

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

class AppJsonSchemaValidate extends \Kojirock5260\Middleware\JsonSchemaValidate
{
    /**
     * Get JsonSchema ClassName.
     * @param \Illuminate\Routing\Route $route
     * @param string                    $type
     * @return string
     */
    public function getJsonSchemaClassName(\Illuminate\Routing\Route $route, string $type): string
    {
        $routeName   = $route->getName();
        $routePrefix = $this->getRoutePrefix($route);
        return "App\\Http\\Schema\\{$routePrefix}\\{$type}\\{$routeName}Schema";
    }

    /**
     * Get Route Prefix.
     * @param \Illuminate\Routing\Route $route
     * @return string
     */
    public function getRoutePrefix(\Illuminate\Routing\Route $route): string
    {
        // prefix = api/admin
        $prefixData = explode('/', $route->getPrefix());

        if (!isset($prefixData[1])) {
            return 'Front';
        }
        return ucfirst($prefixData[1]);
    }
}

```




## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
