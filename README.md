# Laravel JsonSchema Validate


JsonSchema Validate For Laravel Request And Response 

## Installation

```bash
composer require kojirock5260/laravel-json-schema-validate
```

```bash
php artisan vendor:publish --provider=Kojirock5260\\JsonSchemaValidate\\JsonSchemaServiceProvider
```

## Setup

Please describe

### 1. app/Http/Kernel.php

```
    protected $routeMiddleware = [
        ...
        'json_schema' => \Kojirock5260\JsonSchemaValidate\Middleware\JsonSchemaValidator::class,
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

use Kojirock5260\JsonSchemaValidate\SchemaInterface;

class MemberList implements SchemaInterface
{
    public static function getSchema(): array
    {
        return [
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

### 4. Exceptions

* App\Exceptions\Handler.php

```php
    /**
     * Prepare exception for rendering.
     *
     * @param  \Throwable  $e
     * @return \Throwable
     */
    protected function prepareException(Throwable $e)
    {
        if ($e instanceof JsonSchemaException) {
            return ValidationException::withMessages($e->getSchemaErrors());
        }

        return parent::prepareException($e);
    }
```


## Schema Directory Customise

* config/json-schema.php

```php
<?php

return [
    /**
     * Schema Directory Base Namespace
     */
    'namespace' => 'Acme\\Member\\Schema',
];
```


## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
