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

        self::expectExceptionObject(
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

        self::expectExceptionObject(
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
}
