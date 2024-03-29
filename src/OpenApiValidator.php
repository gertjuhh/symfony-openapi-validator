<?php

declare(strict_types=1);

namespace Gertjuhh\SymfonyOpenapiValidator;

use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use League\OpenAPIValidation\Schema\Exception\NotEnoughValidSchemas;
use League\OpenAPIValidation\Schema\Exception\SchemaMismatch;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

trait OpenApiValidator
{
    private static PsrHttpFactory | null $psrHttpFactory = null;

    /** @var array<string, ValidatorBuilder> */
    private static array $validatorBuilder = [];

    /** @throws AssertionFailedError */
    public static function assertOpenApiSchema(string $schema, KernelBrowser $client): void
    {
        $builder = self::getValidatorBuilder($schema);
        $psrFactory = self::getPsrHttpFactory();

        try {
            $match = $builder->getServerRequestValidator()
                ->validate($psrFactory->createRequest($client->getRequest()));
        } catch (ValidationFailed $exception) {
            throw self::wrapValidationException($exception, 'request');
        }

        self::assertResponseAgainstOpenApiSchema($schema, $client, $match);
    }

    public static function assertResponseAgainstOpenApiSchema(
        string $schema,
        KernelBrowser $client,
        OperationAddress | null $operationAddress = null,
    ): void {
        $builder = self::getValidatorBuilder($schema);
        $psrFactory = self::getPsrHttpFactory();

        if ($operationAddress === null) {
            $operationAddress = new OperationAddress(
                path: $client->getRequest()->getPathInfo(),
                method: strtolower($client->getRequest()->getMethod()),
            );
        }

        try {
            $builder->getResponseValidator()
                ->validate($operationAddress, $psrFactory->createResponse($client->getResponse()));
        } catch (ValidationFailed $exception) {
            throw self::wrapValidationException($exception, 'response');
        }
    }

    private static function extractPathFromException(\Throwable $exception): string | null
    {
        if ($exception instanceof SchemaMismatch
            && ($breadcrumb = $exception->dataBreadCrumb())
            // league/openapi-psr7-validator can return an array with a single null value
            // when the breadcrumb's first compoundIndex is null; filter this out here
            && !empty($chain = array_filter($breadcrumb->buildChain()))
        ) {
            return \implode('.', $chain);
        }

        return null;
    }

    private static function getPsrHttpFactory(): PsrHttpFactory
    {
        if (null === self::$psrHttpFactory) {
            $psr17Factory = new Psr17Factory();
            self::$psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
        }

        return self::$psrHttpFactory;
    }

    private static function getValidatorBuilder(string $schema): ValidatorBuilder
    {
        if (!\array_key_exists($schema, self::$validatorBuilder)) {
            self::$validatorBuilder[$schema] = (new ValidatorBuilder())->fromYamlFile($schema);
        }

        return self::$validatorBuilder[$schema];
    }

    private static function wrapValidationException(\Throwable $exception, string $scope): AssertionFailedError
    {
        $message = [$exception->getMessage()];
        $at = null;

        while (null !== ($exception = $exception->getPrevious())) {
            $message[] = $exception->getMessage();

            if (!$at) {
                $at = self::extractPathFromException($exception);
            }

            if ($exception instanceof NotEnoughValidSchemas) {
                foreach ($exception->innerExceptions() as $option => $innerException) {
                    $innerAt = self::extractPathFromException($innerException);

                    $message[] = sprintf(
                        '==> Schema %d: %s%s',
                        ((int)$option) + 1,
                        $innerException->getMessage(),
                        $innerAt ? sprintf(' (at %s)', $innerAt) : '',
                    );
                }
            }
        }

        return new AssertionFailedError(
            \sprintf(
                'OpenAPI %s error%s:%s%s',
                $scope,
                $at !== null
                    ? sprintf(' at %s', $at)
                    : '',
                "\n",
                \implode("\n", $message),
            ),
        );
    }
}

