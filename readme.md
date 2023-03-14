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
