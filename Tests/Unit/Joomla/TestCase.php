<?php

namespace Tests\Unit\Joomla;

use \PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    public function assignMockCallbacks($mockObject, $array): void
    {
        foreach ($array as $index => $method) {
            if (is_array($method)) {
                $methodName = $index;
                $callback = $method;
            } else {
                $methodName = $method;
                $callback = [get_called_class(), 'mock' . $method];
            }

            $mockObject
                ->method($methodName)
                ->willReturnCallback($callback);
        }
    }

    public function assignMockReturns($mockObject, $array): void
    {
        foreach ($array as $method => $return) {
            $mockObject
                ->method($method)
                ->willReturn($return);
        }
    }
}