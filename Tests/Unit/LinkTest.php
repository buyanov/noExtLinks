<?php
namespace Tests\Unit;

use Buyanov\NoExtLinks\Support\Link;
use PHPUnit\Framework\TestCase;

class LinkTest extends TestCase
{

    private $link;

    public function setUp(): void
    {
        $this->link = new Link('https://saity74.ru', 'saity74');
    }

    public function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    public function testGetPropsDefault(): void
    {
        $result = $this->invokeMethod($this->link, 'getProps');
        $expected = 'data-href="https://saity74.ru"';
        $this->assertEquals($expected, $result);
    }

    public function testGetPropsWithTrueArgument(): void
    {
        $result = $this->invokeMethod($this->link, 'getProps', [true]);
        $expected = 'href="https://saity74.ru"';
        $this->assertEquals($expected, $result);
    }

    public function testToString(): void
    {
        $result = (string) $this->link;
        $expected = '<a href="https://saity74.ru">saity74</a>';
        $this->assertEquals($expected, $result);
    }

    public function testAddClass(): void
    {
        $this->link->addClass('test');
        $expected = '<a href="https://saity74.ru" class="test">saity74</a>';
        $this->assertEquals($expected, (string) $this->link);
    }

    public function testSetArgs(): void
    {
        $this->link->setArgs(['target' => '_blank']);
        $expected = '<a target="_blank">saity74</a>';
        $this->assertEquals($expected, (string) $this->link);
    }

    public function testAddArgs(): void
    {
        $this->link->addArgs(['target' => '_blank', 'href' => 'https://google.com']);
        $expected = '<a href="https://saity74.ru" target="_blank">saity74</a>';
        $this->assertEquals($expected, (string) $this->link);
    }

    public function testAddArgsWithClass(): void
    {
        $this->link->addArgs(['target' => '_blank', 'class' => 'test-class']);
        $expected = '<a href="https://saity74.ru" target="_blank" class="test-class">saity74</a>';
        $this->assertEquals($expected, (string) $this->link);
    }

    public function testGetter(): void
    {
        $href = 'https://saity74.ru';
        $this->assertEquals($this->link->__get('href'), $href);
    }

    public function testSetter(): void
    {
        $rel = 'nofollow';
        $this->link->__set('rel', $rel);
        $this->assertEquals($rel, $this->link->rel);
    }

    public function testSetterWithClass(): void
    {
        $class = 'link-class';
        $this->link->__set('class', $class);
        $this->assertFalse(isset($this->link->class));
    }

    public function testIsSet(): void
    {
        $this->link->rel = 'nofollow';
        $this->assertTrue(isset($this->link->rel));
    }

    public function testGetProps(): void
    {
        $this->link->setArgs(['id' => 'linkId', 'rel' => 'nofollow', 'href' => 'http://saity74.ru']);
        $result = $this->invokeMethod($this->link, 'getProps', [false]);
        $expected = 'data-id="linkId" data-rel="nofollow" data-href="http://saity74.ru"';
        $this->assertEquals($expected, $result);
    }

    public function testGetPropWithDataPrefix(): void
    {
        $this->link->setArgs(['id' => 'linkId', 'rel' => 'nofollow', 'href' => 'http://saity74.ru']);
        $result = $this->invokeMethod($this->link, 'getProps', [true]);
        $expected = 'id="linkId" rel="nofollow" href="http://saity74.ru"';
        $this->assertEquals($expected, $result);
    }

    /**
     * @param string $href Property for link
     * @param string $anchor Anchor for link
     * @param string $class Class property for link
     * @param string $expectedResult What we expect our link result to be
     *
     * @dataProvider providerTestCreateLink
     */
    public function testCreate($href, $anchor, $class, $expectedResult): void
    {
        $link = Link::create()
            ->setAnchor($anchor)
            ->addClass($class);

        $link->href = $href;

        $this->assertEquals($expectedResult, (string) $link);
    }

    public function providerTestCreateLink(): array
    {
        return [
            "Create simple link" => [
                'https://saity74.ru',
                'saity74',
                '',
                '<a href="https://saity74.ru">saity74</a>'
            ],
            "Create link without anchor" => [
                'https://saity74.ru',
                '',
                '',
                '<a href="https://saity74.ru"></a>'
            ],
            "Create link with img" => [
                'https://saity74.ru',
                '<img src="test.jpg" alt="" />',
                '',
                '<a href="https://saity74.ru"><img src="test.jpg" alt="" /></a>'
            ],
            "Create link with class prop" => [
                'https://saity74.ru',
                'saity74',
                'test-class',
                '<a href="https://saity74.ru" class="test-class">saity74</a>'
            ]
        ];
    }
}
