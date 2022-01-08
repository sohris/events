<?php

namespace Sohris\Event;

use Doctrine\Common\Annotations\AnnotationReader;
use ReflectionClass;

class Utils
{

    public static function loadAnnotationsOfClass($class)
    {
        $reader = new AnnotationReader();

        $reflection = new ReflectionClass($class);
        $configure = [
            "class" => $reflection,
            "annotations" => $reader->getClassAnnotations($reflection),
            "methods" => self::loadAnnotationsOfClassMethods($reflection)
        ];
        return $configure;
    }

    public static function loadAnnotationsOfClassMethods(ReflectionClass $class)
    {   
        $reader = new AnnotationReader();

        $methods = $class->getMethods();
        $methods_configured = [];

        foreach ($methods as $method) {
            array_push($methods_configured, ["method" => $method, "annotation" => $reader->getMethodAnnotations($method)]);
        }
        return $methods_configured;
    }
}
