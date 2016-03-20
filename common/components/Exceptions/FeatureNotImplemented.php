<?php

namespace common\components\exceptions;
use Yii;
use yii\base\ErrorException;

class FeatureNotImplemented extends ErrorException
{
    protected $ErrorNo;
    protected $message;
    
    public function __construct($message = "ERROR/WARNING" ,$errNo = 0)
    {
        $this->message = $message;
        $this->ErrorNo = $errNo;
        
    }
    
    public function getInfo()
    {
        return "ERROR : " . $this->ErrorNo . " | " . $this->message . PHP_EOL;
    }
}

