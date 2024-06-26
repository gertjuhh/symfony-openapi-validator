# OpenAPI validation for Symfony application tests

This package can validate requests made in application tests, based of Symfony's `WebTestCase`, against OpenAPI
specifications. This is done by converting `HttpFoundation` objects using the
[PSR-7 Bridge](https://symfony.com/doc/current/components/psr7.html) and passing them to the 
[OpenAPI PSR-7 Message Validator](https://github.com/thephpleague/openapi-psr7-validator).

## Installation

```
composer require --dev gertjuhh/symfony-openapi-validator
```

## Usage

- Add the `OpenApiValidator` trait to your `WebTestCase`
- Create a client by calling `self::createClient()`
    - Alternatively use your own custom logic creating an instance of `KernelBrowser`
- Execute the request you wish to validate using the client
- Call `self::assertOpenApiSchema(<schema>, <client>);`
    - `schema`: path to corresponding OpenAPI yaml schema
    - `client`: the client used to make the request
- Or optionally use the `self::assertResponseAgainstOpenApiSchema(<schema>, <client>);` to only validate the response
    - The `operationAddress` can be passed as a third argument for this function but by default it will retrieve the 
      operation from the `client`.

### Setting up a cache

The [underlying library can use a PSR-6 cache](https://github.com/thephpleague/openapi-psr7-validator#caching-layer--psr-6-support).
This provides a significant speedup when running multiple tests against a single schema, since it can be parsed once and 
reused.

In order to activate this cache, you can pass a PSR-6 cache instance to the static property
`\Gertjuhh\SymfonyOpenapiValidator\StaticOpenApiValidatorCache::$validatorCache`. For example:

```php
<?php

use Gertjuhh\SymfonyOpenapiValidator\StaticOpenApiValidatorCache;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

StaticOpenApiValidatorCache::$validatorCache = new ArrayAdapter(storeSerialized: false);
```

Setting `storeSerialized` to false on the ArrayAdapter instance is recommended as it lowers memory usage by storing the actual objects; 
otherwise, Symfony will store a serialized representation of the OpenAPI schema and deserialize it on every test run. 

This snippet can be embedded in a bootstrap script for PHPUnit.

## Example

```PHP
<?php
declare(strict_types=1);

namespace App\ApplicationTests;

use Gertjuhh\SymfonyOpenapiValidator\OpenApiValidator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HelloWorldTest extends WebTestCase
{
    use OpenApiValidator;

    public function testHelloWorldReturnsSuccessfulResponse(): void
    {
        $client = self::createClient();

        $client->xmlHttpRequest('GET', '/hello-world');

        self::assertResponseIsSuccessful();
        self::assertOpenApiSchema('public/openapi.yaml', $client);
    }
}
```
