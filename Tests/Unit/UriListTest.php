<?php

namespace Tests\Unit;

use Buyanov\NoExtLinks\Support\UriList;
use PHPUnit\Framework\TestCase;

class UriListTest extends TestCase
{

    protected $list;

    public function setUp(): void
    {
        $this->list = new UriList();
    }

    public function testNewUriListIsEmpty(): void
    {
        $this->assertEmpty($this->list);
    }

    public function testIsInstanceOfCountable(): void
    {
        $this->assertInstanceOf(\Countable::class, $this->list);
    }

    public function testPushUriToList(): void
    {
        $this->list->push('https://saity74.ru');
        $this->assertNotEmpty($this->list);
        $this->assertCount(1, $this->list);
    }

    public function testFromArray(): void
    {
        $testData = ['https://saity74.ru', 'https://google.com'];
        $count = count($testData);
        $this->list->fromArray($testData);
        $this->assertNotEmpty($this->list);
        $this->assertCount($count, $this->list);
    }

    public function testToArray(): void
    {
        $this->list->push('https://saity74.ru');
        $this->assertCount(1, $this->list->toArray());
        $this->assertIsArray($this->list->toArray());
    }

    public function testMergeUriList(): void
    {
        $list = new UriList();
        $list->push('http://saity74.ru/blah');
        $this->list->push('https://google.com/*');
        $this->list->merge($list);
        $this->assertCount(2, $this->list);
        $this->assertTrue($this->list->exists('http://saity74.ru/blah'));
    }

    public function testPushByParts(): void
    {
        // scheme, host, port, path, query, fragment
        $parts = ['https', 'google.com', '', '/', '', ''];
        $this->list->pushByParts(...$parts);
        $this->assertCount(1, $this->list);
    }

    public function testMap(): void
    {
        $strUri = 'https://google.com';
        $uri = $this->list->map($strUri);
        $this->assertEquals($strUri, $uri->toString());
    }

    public function testExists(): void
    {
        $uri = 'https://saity74.ru';
        $this->list->push($uri);

        $this->assertCount(1, $this->list);
        $this->assertTrue($this->list->exists('https://saity74.ru'));
        $this->assertFalse($this->list->exists('https://google.com'));
    }

    public function testIsMaskedUri(): void
    {
        $uri = '*://saity74.ru';
        $this->assertTrue($this->list->isMasked($uri));
    }

    public function testCompareByParts(): void
    {
        $uri = $this->list->createUri('*://saity74.ru');
        $this->assertTrue($this->list->compareByParts('http://saity74.ru', $uri));
        $this->assertTrue($this->list->compareByParts('https://saity74.ru', $uri));
    }

    public function testCompare(): void
    {
        $this->assertTrue($this->list->compare('http://saity74.ru', 'http://saity74.ru'));
        $uri = $this->list->createUri('*://saity74.ru/*');
        $this->assertTrue($this->list->compare('http://saity74.ru/', $uri));
        $this->assertTrue($this->list->compare('https://saity74.ru/', $uri));
        $this->assertTrue($this->list->compare('https://saity74.ru/blah', $uri));
    }

    /**
     * @param $uri
     *
     * @param string $scheme
     * @param string $host
     * @param string $path
     * @dataProvider dataProviderParseUri
     */
    public function testParseUri($uri, string $scheme, string $host, string $path): void
    {
        $uriParts = $this->list->parseUri($uri);
        $this->assertEquals($scheme, $uriParts['scheme']);
        $this->assertEquals($host, $uriParts['host']);
        $this->assertEquals($path, $uriParts['path']);
    }

    public function dataProviderParseUri(): array
    {
        return [
            'Generic uri' => ['https://saity74.ru/blah', 'https', 'saity74.ru', '/blah'],
            'Scheme masked uri' => ['*://saity74.ru/blah', '*', 'saity74.ru', '/blah'],
            'Path masked uri' => ['http://saity74.ru/*', 'http', 'saity74.ru', '/*'],
            'Masked uri' => ['*://saity74.ru/*', '*', 'saity74.ru', '/*'],
            'Without path' => ['*://saity74.ru', '*', 'saity74.ru', ''],
        ];
    }

    public function testExistsInListWithMasks(): void
    {
        $uri = '*://saity74.ru/*';
        $this->list->push($uri);
        $this->assertCount(1, $this->list);
        $this->assertTrue($this->list->exists('https://saity74.ru/test'));
    }
}
