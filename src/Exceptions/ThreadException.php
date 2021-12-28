<?php
namespace Sohris\Event\Exceptions;

class ThreadException extends \Exception {
    protected $details;
  
    public function __construct($details) {
        $this->details = $details;
        parent::__construct();
    }
  
    public function __toString() {
      return '[LoopException]: ' . $this->details;
    }
  }