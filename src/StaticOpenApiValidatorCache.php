<?php

declare(strict_types=1);

namespace Gertjuhh\SymfonyOpenapiValidator;

use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

final class StaticOpenApiValidatorCache
{
    private static PsrHttpFactory | null $psrHttpFactory = null;

    /** @var array<string, ValidatorBuilder> */
    private static array $validatorBuilder = [];

    /**
     * Install a cache pool for the OpenAPI Validator
     *
     * @see https://github.com/thephpleague/openapi-psr7-validator#caching-layer--psr-6-support
     */
    public static CacheItemPoolInterface|null $validatorCache = null;

    public static function getPsrHttpFactory(): PsrHttpFactory
    {
        if (null === self::$psrHttpFactory) {
            $psr17Factory = new Psr17Factory();
            self::$psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
        }

        return self::$psrHttpFactory;
    }

    public static function getValidatorBuilder(string $schema): ValidatorBuilder
    {
        if (!\array_key_exists($schema, self::$validatorBuilder)) {
            self::$validatorBuilder[$schema] = (new ValidatorBuilder())
                ->fromYamlFile($schema);

            if (self::$validatorCache !== null) {
                self::$validatorBuilder[$schema]->setCache(self::$validatorCache);
            }
        }

        return self::$validatorBuilder[$schema];
    }
}
