<?php

namespace Sohris\Event;

use Doctrine\Common\Annotations\AnnotationReader;
use ReflectionClass;

class Utils
{

    /**
     * Load all classes with extension AbstractEvent and return their Annotations
     * 
     * @return array 
     */
    public static function getAllEvent()
    {

        $all_classes = get_declared_classes();
        $all_events = [];
        foreach ($all_classes as $class) {
            $implenets = class_parents($class);
            if (in_array('Sohris\Event\AbstractEvent', $implenets)) {
                
                array_push($all_events, $class);
            }
        }

        return $all_events;
    }

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

    public static function getAutoload()
    {
        return "./vendor/autoload.php";
    }
}
