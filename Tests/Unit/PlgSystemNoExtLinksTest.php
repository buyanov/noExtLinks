<?php

namespace Tests\Unit;

use Buyanov\NoExtLinks\PlgSystemNoExtLinks;
use Joomla\Registry\Registry;
use Tests\Unit\Joomla\Core\Mock\TestMockApplication;
use Tests\Unit\Joomla\Core\Mock\TestMockDispatcher;
use Tests\Unit\Joomla\TestCase;

class PlgSystemNoExtLinksTest extends TestCase
{

    protected function createPluginWithParams(array $params, array $appConfig = []): PlgSystemNoExtLinks
    {
        $dispatcher = TestMockDispatcher::create($this);

        $plugin = array(
            'name'   => 'noextlinks',
            'type'   => 'System',
            'params' => new Registry($params)
        );

        $_REQUEST['Itemid'] = 42;
        $_REQUEST['url'] = 'http://saity74.ru';

        $class = new PlgSystemNoExtLinks($dispatcher, $plugin);

        $app = TestMockApplication::create($this, $appConfig);
        $reflection = new \ReflectionObject($class);
        $appProperty = $reflection->getProperty('app');
        $appProperty->setAccessible('true');
        $appProperty->setValue($class, $app);

        return $class;
    }

    /**
     * @param $expected
     * @param $params
     *
     * @dataProvider onBeforeRenderParamsDataProvider
     */
    public function testOnBeforeRender($expected, $params): void
    {
        $class = $this->createPluginWithParams($params);

        $this->assertEquals($expected, $class->onBeforeRender());
    }

    public function onBeforeRenderParamsDataProvider(): array
    {
        return [
            [
                true,
                [
                    'use_redirect_page' => true,
                    'redirect_page' => 42
                ],
            ],
            [
                true,
                [
                    'use_redirect_page' => false,
                    'redirect_page' => 1
                ]
            ]
        ];
    }

    /**
     *
     * @dataProvider onAfterRenderParamsDataProvider
     * @param $expected
     * @param $params
     */
    public function testOnAfterRender($expected, $params): void
    {
        $class = $this->createPluginWithParams($params);
        $this->assertEquals($expected, $class->onAfterRender());
    }

    public function onAfterRenderParamsDataProvider(): array
    {
        return [
            [
                true,
                [
                    'use_redirect_page' => true,
                    'redirect_page' => 1
                ],
            ],
        ];
    }

    public function testOnAfterRenderInAdmin(): void
    {
        $class = $this->createPluginWithParams([]);
        $class->getApp()->method('isAdmin')
            ->willReturn(true);

        $this->assertTrue($class->onAfterRender());
    }

    public function testOnAfterRenderWithOptions(): void
    {
        $_REQUEST['option'] = 'com_content';
        $_REQUEST['view'] = 'article';
        $_REQUEST['id'] = '1';

        $class = $this->createPluginWithParams([
            'excluded_articles' => 1,
            'excluded_category_list' => 1,
            'excluded_menu_items' => '42,43,44',
            'whitelist' => "https://google.ru/*",
            'removed_domains' => '{"host":["saity74.ru"]}',
            'excluded_domains' => '{"scheme":["https"], "host":["google.com"], "path":["/*"]}'
        ]);

        $class->getApp()->setBody('<html><body><a href="#">link</a></body></html>');

        $this->assertTrue($class->onAfterRender());
    }

    public function testOnAfterRenderWithoutMenu(): void
    {
        $_REQUEST['option'] = 'com_content';
        $_REQUEST['view'] = 'blog';
        $_REQUEST['id'] = '2';

        $class = $this->createPluginWithParams([
            'excluded_menu_items' => '42,43,44',
        ]);

        $class->getApp()->setBody('<html><body><a href="#">link</a></body></html>');

        $this->assertTrue($class->onAfterRender());
    }

    public function testOnAfterRenderWithoutActiveItem(): void
    {
        $_REQUEST['option'] = 'com_content';
        $_REQUEST['view'] = 'blog';
        $_REQUEST['id'] = '2';

        $class = $this->createPluginWithParams([
            'excluded_menu_items' => '42,43,44',
        ], ['withMenu' => true]);

        $class->getApp()->setBody('<html><body><a href="#">link</a></body></html>');

        $this->assertTrue($class->onAfterRender());
    }

    public function testOnAfterRenderWithMenuAndActiveItem(): void
    {
        $_REQUEST['option'] = 'com_content';
        $_REQUEST['view'] = 'blog';
        $_REQUEST['id'] = '2';

        $class = $this->createPluginWithParams([
            'excluded_menu_items' => '42,43,44',
        ],['withMenu' => true, 'activeItem' => 42]);

        $class->getApp()->setBody('<html><body><a href="#">link</a></body></html>');

        $this->assertTrue($class->onAfterRender());
    }

    public function testOnAfterRenderWithExcludedCategoriesFalse(): void
    {
        $_REQUEST['option'] = 'com_content';
        $_REQUEST['view'] = 'blog';
        $_REQUEST['id'] = '5';

        $class = $this->createPluginWithParams([
            'excluded_category_list' => '1',
            'excluded_categories' => '2,3',
            'usejs' => '1'
        ]);

        $class->getApp()->setBody('<html><body><a href="#">link</a></body></html>');

        $this->assertTrue($class->onAfterRender());
    }

    public function testOnAfterRenderWithExcludedCategoriesTrue(): void
    {
        $_REQUEST['option'] = 'com_content';
        $_REQUEST['view'] = 'blog';
        $_REQUEST['id'] = '2';

        $class = $this->createPluginWithParams([
            'excluded_categories' => '2,3',
        ]);

        $class->getApp()->setBody('<html><body><a href="#">link</a></body></html>');

        $this->assertTrue($class->onAfterRender());
    }
}