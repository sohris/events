<?php

namespace Sohris\Event;

use Doctrine\Common\Annotations\AnnotationReader;
use ReflectionClass;
use Sohris\Core\Server;
use Sohris\Core\Utils as CoreUtils;

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


    public static function getSavedConfigurationEvents(string $event_name)
    {
        $file_config = Server::getRootDir() . DIRECTORY_SEPARATOR . Event::EVENT_FILE_NAME;
        if (!CoreUtils::checkFileExists($file_config)) return false;

        $file_contents = file_get_contents($file_config);
        $configs = json_decode($file_contents, true);
        if (empty($configs) || !array_key_exists($event_name, $configs)) return false;
        
        return $configs[$event_name];
    }
}
