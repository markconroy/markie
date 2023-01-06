<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

/**
 * Converts between objects with getter and setter methods and arrays.
 *
 * The normalization process looks at all public methods and calls the ones
 * which have a name starting with get and take no parameters. The result is a
 * map from property names (method name stripped of the get prefix and converted
 * to lower case) to property values. Property values are normalized through the
 * serializer.
 *
 * The denormalization first looks at the constructor of the given class to see
 * if any of the parameters have the same name as one of the properties. The
 * constructor is then called with all parameters or an exception is thrown if
 * any required parameters were not present as properties. Then the denormalizer
 * walks through the given map of property names to property values to see if a
 * setter method exists for any of the properties. If a setter exists it is
 * called with the property value. No automatic denormalization of the value
 * takes place.
 *
 * @author Nils Adermann <naderman@naderman.de>
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class GetSetMethodNormalizer extends AbstractObjectNormalizer
{
    private static $setterAccessibleCache = [];

    /**
     * @param array $context
     */
    public function supportsNormalization(mixed $data, string $format = null /* , array $context = [] */): bool
    {
        return parent::supportsNormalization($data, $format) && $this->supports($data::class);
    }

    /**
     * @param array $context
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null /* , array $context = [] */): bool
    {
        return parent::supportsDenormalization($data, $type, $format) && $this->supports($type);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === static::class;
    }

    /**
     * Checks if the given class has any getter method.
     */
    private function supports(string $class): bool
    {
        $class = new \ReflectionClass($class);
        $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            if ($this->isGetMethod($method)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a method's name matches /^(get|is|has).+$/ and can be called non-statically without parameters.
     */
    private function isGetMethod(\ReflectionMethod $method): bool
    {
        $methodLength = \strlen($method->name);

        return
            !$method->isStatic() &&
            (
                ((str_starts_with($method->name, 'get') && 3 < $methodLength) ||
                (str_starts_with($method->name, 'is') && 2 < $methodLength) ||
                (str_starts_with($method->name, 'has') && 3 < $methodLength)) &&
                0 === $method->getNumberOfRequiredParameters()
            )
        ;
    }

    protected function extractAttributes(object $object, string $format = null, array $context = []): array
    {
        $reflectionObject = new \ReflectionObject($object);
        $reflectionMethods = $reflectionObject->getMethods(\ReflectionMethod::IS_PUBLIC);

        $attributes = [];
        foreach ($reflectionMethods as $method) {
            if (!$this->isGetMethod($method)) {
                continue;
            }

            $attributeName = lcfirst(substr($method->name, str_starts_with($method->name, 'is') ? 2 : 3));

            if ($this->isAllowedAttribute($object, $attributeName, $format, $context)) {
                $attributes[] = $attributeName;
            }
        }

        return $attributes;
    }

    protected function getAttributeValue(object $object, string $attribute, string $format = null, array $context = []): mixed
    {
        $ucfirsted = ucfirst($attribute);

        $getter = 'get'.$ucfirsted;
        if (method_exists($object, $getter) && \is_callable([$object, $getter])) {
            return $object->$getter();
        }

        $isser = 'is'.$ucfirsted;
        if (method_exists($object, $isser) && \is_callable([$object, $isser])) {
            return $object->$isser();
        }

        $haser = 'has'.$ucfirsted;
        if (method_exists($object, $haser) && \is_callable([$object, $haser])) {
            return $object->$haser();
        }

        return null;
    }

    protected function setAttributeValue(object $object, string $attribute, mixed $value, string $format = null, array $context = [])
    {
        $setter = 'set'.ucfirst($attribute);
        $key = $object::class.':'.$setter;

        if (!isset(self::$setterAccessibleCache[$key])) {
            try {
                // We have to use is_callable() here since method_exists()
                // does not "see" protected/private methods
                self::$setterAccessibleCache[$key] = \is_callable([$object, $setter]) && !(new \ReflectionMethod($object, $setter))->isStatic();
            } catch (\ReflectionException $e) {
                // Method does not exist in the class, probably a magic method
                self::$setterAccessibleCache[$key] = false;
            }
        }

        if (self::$setterAccessibleCache[$key]) {
            $object->$setter($value);
        }
    }
}
