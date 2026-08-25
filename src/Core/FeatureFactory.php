<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core;

use EICC\Utils\Container;
use EICC\Utils\Log;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

/**
 * Builds Feature (and, recursively, their constructor dependencies) via
 * reflection. A container entry registered under the parameter's type wins;
 * otherwise the type is autowired recursively. There is no `new $class()`
 * fallback — a parameter that cannot be resolved is a load failure, not a
 * silent construction with missing dependencies.
 */
class FeatureFactory
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @throws RuntimeException
     */
    public function make(string $className): object
    {
        if ($this->container->has($className)) {
            return $this->container->get($className);
        }

        if (!class_exists($className)) {
            throw new RuntimeException("Class {$className} does not exist");
        }

        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return $reflection->newInstance();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $arguments[] = $this->resolveParameter($className, $parameter);
        }

        return $reflection->newInstanceArgs($arguments);
    }

    /**
     * @throws RuntimeException
     */
    private function resolveParameter(string $forClass, ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $typeName = $type->getName();

            if ($typeName === Container::class) {
                return $this->container;
            }

            if ($typeName === Log::class && $this->container->has('logger')) {
                return $this->container->get('logger');
            }

            if ($this->container->has($typeName)) {
                return $this->container->get($typeName);
            }

            if (class_exists($typeName)) {
                return $this->make($typeName);
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($type instanceof ReflectionNamedType && $type->allowsNull()) {
            return null;
        }

        throw new RuntimeException(sprintf(
            'Cannot resolve parameter $%s for %s: no matching container entry, ' .
            'autowirable class, or default value.',
            $parameter->getName(),
            $forClass
        ));
    }
}
