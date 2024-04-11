<?php

namespace Sohris\Event\Annotations;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
class Group
{

    public $group = 'default';

    public function __construct($args)
    {
        $this->group = trim(strtolower($args['value']));
    }
}
