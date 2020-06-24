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
 * Class to mock JLanguage.
 *
 * @package  Joomla.Test
 * @since    3.0.0
 */
class TestMockLanguage
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
            '_',
            'getInstance',
            'getTag',
            'isRTL',
            'test',
        );

        // Build the mock object.
        $mockObject = $test->getMockBuilder('JLanguage')
            ->setMethods($methods)
            ->setConstructorArgs(array())
            ->setMockClassName('')
            ->disableOriginalConstructor()
            ->getMock();

        // Mock selected methods.
        $test->assignMockReturns(
            $mockObject, array(
                'getInstance' => $mockObject,
                'getTag' => 'en-GB',
                'isRTL' => false,
                // An additional 'test' method for confirming this object is successfully mocked.
                'test' => 'ok',
            )
        );

        $test->assignMockCallbacks(
            $mockObject,
            array(
                '_' => array(get_called_class(), 'mock_'),
            )
        );

        return $mockObject;
    }

    /**
     * Callback for the mock JLanguage::_ method.
     *
     * @param   string   $string                The string to translate
     * @param   boolean  $jsSafe                Make the result javascript safe
     * @param   boolean  $interpretBackSlashes  Interpret \t and \n
     *
     * @return string
     *
     * @since  1.7.3
     */
    public static function mock_($string, $jsSafe = false, $interpretBackSlashes = true)
    {
        return $string;
    }
}