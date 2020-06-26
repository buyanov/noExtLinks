<?php

namespace Tests\Unit\Joomla\Core\Mock;

use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\Joomla\TestCase;

/**
 * @package    Joomla.Test
 *
 * @copyright  Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Class to mock JDocument.
 *
 * @package  Joomla.Test
 * @since    3.0.0
 */
class TestMockDocument
{
    /**
     * Creates and instance of the mock JLanguage object.
     *
     * @param   TestCase  $test  A test object.
     *
     * @return  MockObject
     *
     * @since   1.7.3
     */
    public static function create($test): MockObject
    {
        // Collect all the relevant methods in JDatabase.
        $methods = array(
            'parse',
            'setMetaData',
            'render'
        );

        // Create the mock.
        $mockObject = $test->getMockBuilder('JDocument')
            ->setMethods($methods)
            ->setConstructorArgs(array())
            ->setMockClassName('')
            ->disableOriginalConstructor()
            ->getMock();

        return $mockObject;
    }
}