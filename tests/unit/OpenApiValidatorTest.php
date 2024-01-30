<?php
declare(strict_types=1);

namespace Gertjuhh\SymfonyOpenapiValidator\UnitTests;

use Gertjuhh\SymfonyOpenapiValidator\OpenApiValidator;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class OpenApiValidatorTest extends TestCase
{
    use OpenApiValidator;

    public function testValidatorDoesNothingWhenRequestAndResponseAreValid(): void
    {
        $request = Request::create(uri: 'https://localhost/hello-world', server: [
            'HTTP_X_REQUESTED_WITH',
            'XMLHttpRequest',
        ]);
        $response = new JsonResponse(['hello' => 'world']);

        $browser = $this->createMock(KernelBrowser::class);
        $browser->expects(self::once())
            ->method('getRequest')
            ->willReturn($request);
        $browser->expects(self::once())
            ->method('getResponse')
            ->willReturn($response);

        self::assertOpenApiSchema('tests/openapi.yaml', $browser);
    }

    public function testValidatorThrowsErrorWhenRequestIsInvalid(): void
    {
        $request = Request::create(uri: 'https://localhost/hello', server: ['HTTP_X_REQUESTED_WITH', 'XMLHttpRequest']);

        $browser = $this->createMock(KernelBrowser::class);
        $browser->expects(self::once())
            ->method('getRequest')
            ->willReturn($request);

        $this->expectExceptionObject(
            new AssertionFailedError(
                \sprintf(
                    '%s%s%s',
                    'OpenAPI request error:',
                    "\n",
                    'OpenAPI spec contains no such operation [/hello,get]'
                )
            )
        );

        self::assertOpenApiSchema('tests/openapi.yaml', $browser);
    }

    public function testValidatorThrowsErrorWhenResponseIsInvalid(): void
    {
        $request = Request::create(uri: 'https://localhost/hello-world', server: [
            'HTTP_X_REQUESTED_WITH',
            'XMLHttpRequest',
        ]);
        $response = new JsonResponse(['foo' => 'bar'], headers: ['content-type' => 'application/json']);

        $browser = $this->createMock(KernelBrowser::class);
        $browser->expects(self::once())
            ->method('getRequest')
            ->willReturn($request);
        $browser->expects(self::once())
            ->method('getResponse')
            ->willReturn($response);

        $this->expectExceptionObject(
            new AssertionFailedError(
                \sprintf(
                    '%s%s%s%s%s',
                    'OpenAPI response error at hello:',
                    "\n",
                    'Body does not match schema for content-type "application/json" for Response [get /hello-world 200]',
                    "\n",
                    'Keyword validation failed: Required property \'hello\' must be present in the object'
                )
            )
        );

        self::assertOpenApiSchema('tests/openapi.yaml', $browser);
    }

    public function testValidatorThrowsErrorContainingInnerExceptionsWhenResponseFailsOneOfValidation(): void
    {
        $request = Request::create(uri: 'https://localhost/match-oneof', server: [
            'HTTP_X_REQUESTED_WITH',
            'XMLHttpRequest',
        ]);
        $response = new JsonResponse(['foo' => 'bar'], headers: ['content-type' => 'application/json']);

        $browser = $this->createMock(KernelBrowser::class);
        $browser->expects(self::once())
            ->method('getRequest')
            ->willReturn($request);
        $browser->expects(self::once())
            ->method('getResponse')
            ->willReturn($response);

        $this->expectExceptionObject(
            new AssertionFailedError(
                \implode(
                    "\n",
                    [
                        'OpenAPI response error:',
                        'Body does not match schema for content-type "application/json" for Response [get /match-oneof 200]',
                        'Keyword validation failed: Data must match exactly one schema, but matched none',
                        '==> Schema 1: Keyword validation failed: Required property \'hello\' must be present in the object (at hello)',
                        '==> Schema 2: Keyword validation failed: Required property \'nested\' must be present in the object (at nested)',
                    ],
                ),
            )
        );

        self::assertOpenApiSchema('tests/openapi.yaml', $browser);
    }

    public function testValidatorThrowsErrorWhenNestedResponseIsInvalid(): void
    {
        $request = Request::create(uri: 'https://localhost/nested-property', server: [
            'HTTP_X_REQUESTED_WITH',
            'XMLHttpRequest',
        ]);
        $response = new JsonResponse(['nested' => new \stdClass()], headers: ['content-type' => 'application/json']);

        $browser = $this->createMock(KernelBrowser::class);
        $browser->expects(self::once())
            ->method('getRequest')
            ->willReturn($request);
        $browser->expects(self::once())
            ->method('getResponse')
            ->willReturn($response);

        $this->expectExceptionObject(
            new AssertionFailedError(
                \sprintf(
                    '%s%s%s%s%s',
                    'OpenAPI response error at nested.property:',
                    "\n",
                    'Body does not match schema for content-type "application/json" for Response [get /nested-property 200]',
                    "\n",
                    'Keyword validation failed: Required property \'property\' must be present in the object'
                )
            )
        );

        self::assertOpenApiSchema('tests/openapi.yaml', $browser);
    }

    public function testValidatorThrowsErrorWhenInputIsInvalid(): void
    {
        $request = Request::create(
            uri: 'https://localhost/input-validation',
            method: 'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: \json_encode(['email' => 'john.doe'], \JSON_THROW_ON_ERROR),
        );

        $browser = $this->createMock(KernelBrowser::class);
        $browser->expects(self::once())
            ->method('getRequest')
            ->willReturn($request);

        $this->expectExceptionObject(new AssertionFailedError(
            \sprintf(
                '%s%s%s%s%s',
                'OpenAPI request error at email:',
                "\n",
                'Body does not match schema for content-type "application/json" for Request [post /input-validation]',
                "\n",
                'Value \'john.doe\' does not match format email of type string',
            )
        ));

        self::assertOpenApiSchema('tests/openapi.yaml', $browser);
    }

    public function testResponseValidatorWillThrowErrorWhenErrorResponseIsInvalid(): void
    {
        $request = Request::create(
            uri: 'https://localhost/input-validation',
            method: 'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: \json_encode(['email' => 'john.doe'], \JSON_THROW_ON_ERROR),
        );
        $response = new JsonResponse(data: ['output' => 'invalid'], status: 422);

        $browser = $this->createMock(KernelBrowser::class);
        $browser->expects(self::exactly(2))
            ->method('getRequest')
            ->willReturn($request);
        $browser->expects(self::once())
            ->method('getResponse')
            ->willReturn($response);

        $this->expectExceptionObject(new AssertionFailedError(
            \sprintf(
                '%s%s%s%s%s',
                'OpenAPI response error at message:',
                "\n",
                'Body does not match schema for content-type "application/json" for Response [post /input-validation 422]',
                "\n",
                'Keyword validation failed: Required property \'message\' must be present in the object',
            )
        ));

        self::assertResponseAgainstOpenApiSchema('tests/openapi.yaml', $browser);
    }

    public function testResponseValidatorDoesNothingWhenResponseIsValid(): void
    {
        $request = Request::create(
            uri: 'https://localhost/input-validation',
            method: 'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: \json_encode(['email' => 'john.doe'], \JSON_THROW_ON_ERROR),
        );
        $response = new JsonResponse(data: ['message' => 'invalid'], status: 422);

        $browser = $this->createMock(KernelBrowser::class);
        $browser->expects(self::exactly(2))
            ->method('getRequest')
            ->willReturn($request);
        $browser->expects(self::once())
            ->method('getResponse')
            ->willReturn($response);

        self::assertResponseAgainstOpenApiSchema('tests/openapi.yaml', $browser);
    }
}
