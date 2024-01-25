<?php

declare(strict_types=1);

namespace Gertjuhh\SymfonyOpenapiValidator;

use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
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

        try {
            $builder->getResponseValidator()
                ->validate($match, $psrFactory->createResponse($client->getResponse()));
        } catch (ValidationFailed $exception) {
            throw self::wrapValidationException($exception, 'response');
        }
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

            if (!$at && $exception instanceof SchemaMismatch && $breadcrumb = $exception->dataBreadCrumb()) {
                $at = \implode('.', $breadcrumb->buildChain());
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

