<?php
namespace Buyanov\NoExtLinks\Support;

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
        $this->link->addArgs(['target' => '_blank']);
        $expected = '<a href="https://saity74.ru" target="_blank">saity74</a>';
        $this->assertEquals($expected, (string) $this->link);
    }

    public function testAddArgsWithClass(): void
    {
        $this->link->addArgs(['target' => '_blank', 'class' => 'test-class']);
        $expected = '<a href="https://saity74.ru" target="_blank" class="test-class">saity74</a>';
        $this->assertEquals($expected, (string) $this->link);
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
        $link = Link::create();
        $link->href = $href;
        $link->setAnchor($anchor);
        $link->addClass($class);

        $this->assertEquals($expectedResult, (string) $link);
    }

    public function providerTestCreateLink(): array
    {
        return [
            "Create simple link" => ['https://saity74.ru', 'saity74', '', '<a href="https://saity74.ru">saity74</a>'],
            "Create link without anchor" => ['https://saity74.ru', '', '', '<a href="https://saity74.ru"></a>'],
            "Create link with img" => ['https://saity74.ru', '<img src="test.jpg" />', '', '<a href="https://saity74.ru"><img src="test.jpg" /></a>'],
            "Create link with class prop" =>  ['https://saity74.ru', 'saity74', 'test-class', '<a href="https://saity74.ru" class="test-class">saity74</a>']
        ];
    }
}
