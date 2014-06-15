<?php
namespace League\Mantle;

use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @author Ramon Kleiss <ramonkleiss@gmail.com>
 */
class Mantle
{
    /**
     * @param array|stdClass $json
     * @param string         $class
     *
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function transform($json, $class)
    {
        if (is_array($json)) {
            return $this->transformArray($json, $class);
        } elseif ($json instanceof \stdClass) {
            return $this->transformObject($json, $class);
        }

        throw new \InvalidArgumentException('The $json argument must be either an array or an instance of stdClass');
    }

    /**
     * @param array  $json
     * @param string $class
     *
     * @return mixed
     */
    private function transformArray(array $json, $class)
    {
        return array_map(function ($json) use ($class) {
            return $this->transformObject($json, $class);
        }, $json);
    }

    /**
     * @param stdClass $json
     * @param string   $class
     *
     * @return mixed
     */
    private function transformObject(\stdClass $json, $class)
    {
        $object = new $class();
        $mapping = $this->getPropertyMapping($object);

        foreach ($mapping as $destination => $source) {
            $this->map($json, $source, $object, $destination);
        }

        return $object;
    }

    /**
     * @param mixed $object
     *
     * @return array
     */
    private function getPropertyMapping($object)
    {
        $mapping = array();
        $reflection = new \ReflectionClass($object);

        // Create a default property mapping by getting the properties
        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();
            $setterMethod = 'set' . ucfirst($name);

            if (method_exists($object, $setterMethod) || $property->isPublic()) {
                $mapping[$name] = $name;
            }
        }

        // If the getPropertyMapping method exists, merge it with the
        // existing mapping.
        //
        // This comes last because it has higher priority than the default
        // mapping.
        if (method_exists($object, 'getPropertyMapping')) {
            $mapping = array_merge($mapping, $object->getPropertyMapping());
        }

        // Filter any properties that are mapped to null
        foreach ($mapping as $destination => $source) {
            if (!$source) {
                unset($mapping[$destination]);
            }
        }

        return $mapping;
    }

    /**
     * @param stdClass $json
     * @param string   $source
     * @param mixed    $object
     * @param string   $destination
     */
    private function map(\stdClass $json, $source, $object, $destination)
    {
        $accessor = PropertyAccess::createPropertyAccessor();

        // Try to get the value from the JSON source property
        try {
            $value = $accessor->getValue($json, $source);
        } catch (\Exception $e) {
            try {
                $value = $accessor->getValue($json, $this->camelCaseToSnakeCase($source));
            } catch (\Exception $e) {
                return;
            }
        }

        // var_dump($source, $value, $destination, '---');

        // Check if a transformer exists for the property and transform the value
        $transformerMethod = $destination .'Transformer';

        if (method_exists($object, $transformerMethod)) {
            $value = call_user_func_array(
                array($object, $transformerMethod),
                array($value)
            );
        }

        // Check if a class is declared for the property and transform the value
        $classMethod = $destination .'Class';

        if (method_exists($object, $classMethod)) {
            $value = $this->transform($value, $object->$classMethod());
        }

        // Set the value on the object
        $accessor->setValue($object, $destination, $value);
    }

    /**
     * @param string $string
     *
     * @return string
     *
     * @see http://stackoverflow.com/questions/1993721/how-to-convert-camelcase-to-camel-case
     */
    private function camelCaseToSnakeCase($string)
    {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $string, $matches);
        $ret = $matches[0];

        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }

        return implode('_', $ret);
    }
}
