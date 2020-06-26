<?php

namespace Tests\Unit\Joomla\Core\Mock;

use Joomla\Input\Input as JInput;

/**
 * @package    Joomla.Test
 *
 * @copyright  Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Class to mock JApplication.
 *
 * @package  Joomla.Test
 * @since    3.0.0
 */
class TestMockApplication
{
    public static $body;

    /**
     * Creates and instance of the mock JApplication object.
     *
     * @param object $test A test object.
     *
     * @param array $config
     *
     * @return  object
     *
     * @since   1.7.3
     */
    public static function create($test, array $config = [])
    {
        // Collect all the relevant methods in JApplication (work in progress).
        $methods = array(
            'get',
            'getCfg',
            'getIdentity',
            'getRouter',
            'getTemplate',
            'getDocument',
            'getMenu',
            'getLanguage',
            'isAdmin',
            'appendBody',
            'getBody',
            'prependBody',
            'setBody'
        );

        // Build the mock object.
        $mockObject = $test->getMockBuilder('JApplication')
            ->setMethods($methods)
            ->setConstructorArgs(array())
            ->setMockClassName('')
            ->disableOriginalConstructor()
            ->getMock();

        if (isset($config['withMenu'])) {
            $menu = TestMockMenu::create($test, true, $config['activeItem'] ?? false);
            $mockObject->expects($test->any())
                ->method('getMenu')
                ->willReturn($menu);
        }


        $language = TestMockLanguage::create($test);
        $mockObject->expects($test->any())
            ->method('getLanguage')
            ->willReturn($language);

        $document = TestMockDocument::create($test);
        $mockObject->expects($test->any())
            ->method('getDocument')
            ->willReturn($document);

        $mockObject->input = new JInput();

        $test->assignMockCallbacks(
            $mockObject,
            [
                'appendBody' => [(is_callable([$test, 'mockAppendBody']) ? $test : get_called_class()), 'mockAppendBody'],
                'getBody' => [(is_callable([$test, 'mockGetBody']) ? $test : get_called_class()), 'mockGetBody'],
                'prependBody' => [(is_callable([$test, 'mockPrependBody']) ? $test : get_called_class()), 'mockPrependBody'],
                'setBody' => [(is_callable([$test, 'mockSetBody']) ? $test : get_called_class()), 'mockSetBody'],
            ]
        );

        return $mockObject;
    }


    /**
     * Mock JApplicationWeb->appendBody method.
     *
     * @param   string  $content  The content to append to the response body.
     *
     * @return  mixed
     *
     * @since   3.0.1
     */
    public static function mockAppendBody($content)
    {
        static::$body[] = (string) $content;
    }

    /**
     * Mock JApplicationWeb->getBody method.
     *
     * @param   boolean  $asArray  True to return the body as an array of strings.
     *
     * @return  mixed
     *
     * @since   3.0.1
     */
    public static function mockGetBody($asArray = false)
    {
        return $asArray ? static::$body : implode((array) static::$body);
    }

    /**
     * Mock JApplicationWeb->appendBody method.
     *
     * @param   string  $content  The content to append to the response body.
     *
     * @return  mixed
     *
     * @since   3.0.1
     */
    public static function mockPrependBody($content)
    {
        array_unshift(static::$body, (string) $content);
    }

    /**
     * Mock JApplicationWeb->setBody method.
     *
     * @param   string  $content  The body of the response.
     *
     * @return  void
     *
     * @since   3.0.1
     */
    public static function mockSetBody($content)
    {
        static::$body = array($content);
    }

}