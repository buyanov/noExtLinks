<?php
namespace Buyanov\NoExtLinks\Support;

use Joomla\Registry\Registry;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{

    public function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    public function getOptions(array $options = []): Registry
    {
        $result = [];
        $optionsFile = __DIR__ . '/../src/noextlinks.xml';
        if (file_exists($optionsFile)) {
            $xml = simplexml_load_string(file_get_contents($optionsFile));
            $fieldsets = $xml->xpath('/extension/config/fields/fieldset');
            foreach ($fieldsets as $fieldset) {
                foreach ($fieldset->children() as $field) {
                    $attributes = get_object_vars($field->attributes())['@attributes'];
                    $result[$attributes['name']] = $attributes['default'] ?? '';
                }
            }
        }

        $result = new Registry(array_merge($result, $options));

        return $result;
    }

    /**
     * @param string $content
     * @param array $options
     * @param string $expectedResult
     *
     * @dataProvider providerTestCreateLink
     */
    public function testParserWithoutListsAndCallback($content, array $options, $expectedResult): void
    {
        $options = $this->getOptions($options);

        Parser::create($content, $options)
            ->prepare()
            ->parse()
            ->finish();

        $this->assertEquals($expectedResult, $content);
    }

    public function providerTestCreateLink(): array
    {
        return [
            "Empty" => [
                '',
                [],
                ''
            ],
            "Text without links" => [
                'test text',
                [],
                'test text'
            ],
            "Simple internal link" => [
                'Test <a href="/test">link</a> with text',
                [],
                'Test <a href="/test">link</a> with text'
            ],
            "Simple internal link absolutize=on" => [
                'Test <a href="/test">link</a> with text',
                ['absolutize' => "1"],
                'Test <a href="http://localhost/test">link</a> with text'
            ],
            "External link without anchor" => [
                'Test <a href="https://google.com"></a> with text',
                [],
                'Test <!--noindex--><a href="https://google.com" target="_blank" rel="nofollow" class="external-link"></a><!--/noindex--> with text'
            ],
            "Call-able link" => [
                'Test <a href="tel:123-456-7890">123-456-7890</a> with text',
                [],
                'Test <a href="tel:123-456-7890">123-456-7890</a> with text'
            ],
            "Skype call-able link" => [
                'Test <a href="skype:saity74?call">Click</a> with text',
                [],
                'Test <a href="skype:saity74?call">Click</a> with text',
            ],
            "WhatsUp call-able link" => [
                'Test <a href="whatsapp://send?text=sometext"data-action="share/whatsapp/share">WhatsApp</a> with text',
                [],
                'Test <a href="whatsapp://send?text=sometext"data-action="share/whatsapp/share">WhatsApp</a> with text',
            ],
            "External link with default options" => [
                'Test <a href="https://google.com">google</a> with text',
                [],
                'Test <!--noindex--><a href="https://google.com" target="_blank" title="google" rel="nofollow" class="external-link --set-title">google</a><!--/noindex--> with text'
            ],
            "External link with class prop and with default options" => [
                'Test <a href="https://google.com" class="my-custom-class">google</a> with text',
                [],
                'Test <!--noindex--><a href="https://google.com" target="_blank" title="google" rel="nofollow" class="my-custom-class external-link --set-title">google</a><!--/noindex--> with text'
            ],
            "External link with title prop and with default options" => [
                'Test <a href="https://google.com" title="link title" class="my-custom-class">google</a> with text',
                [],
                'Test <!--noindex--><a href="https://google.com" title="link title" target="_blank" rel="nofollow" class="my-custom-class external-link">google</a><!--/noindex--> with text'
            ],
            "External link in white block <!-- extlinks -->" => [
                '<!-- extlinks -->Test <a href="https://google.com" title="link title" class="my-custom-class">google</a> with text<!-- /extlinks -->',
                [],
                '<!-- extlinks -->Test <a href="https://google.com" title="link title" class="my-custom-class">google</a> with text<!-- /extlinks -->'
            ],
            "External link with img tag inside" => [
                'Test <a href="https://google.com" title="link title" class="my-custom-class"><img src="some.jpg alt="some description"/></a> with text',
                [],
                'Test <!--noindex--><a href="https://google.com" title="link title" target="_blank" rel="nofollow" class="my-custom-class external-link"><img src="some.jpg alt="some description"/></a><!--/noindex--> with text'
            ],
            "External link with noindex=off" => [
                'Test <a href="https://google.com" title="link title" class="my-custom-class">google</a> with text',
                ['noindex' => '0'],
                'Test <a href="https://google.com" title="link title" target="_blank" rel="nofollow" class="my-custom-class external-link">google</a> with text'
            ],
            "External link with nofollow=off" => [
                'Test <a href="https://google.com" title="link title" class="my-custom-class">google</a> with text',
                ['noindex' => '0', 'nofollow' => "0"],
                'Test <a href="https://google.com" title="link title" target="_blank" class="my-custom-class external-link">google</a> with text'
            ],
            "External link with settitle=off" => [
                'Test <a href="https://google.com" title="link title" class="my-custom-class">google</a> with text',
                ['noindex' => '0', 'nofollow' => "0", "settitle" => "0"],
                'Test <a href="https://google.com" title="link title" target="_blank" class="my-custom-class external-link">google</a> with text'
            ],
            "External link with settitle=on" => [
                'Test <a href="https://google.com" class="my-custom-class">google</a> with text',
                ['noindex' => '0', 'nofollow' => "0", "settitle" => "1"],
                'Test <a href="https://google.com" target="_blank" title="google" class="my-custom-class external-link --set-title">google</a> with text'
            ],
            "External link with blank=off" => [
                'Test <a href="https://google.com" class="my-custom-class">google</a> with text',
                ['noindex' => '0', 'nofollow' => "0", "settitle" => "0", "blank" => "0"],
                'Test <a href="https://google.com" class="my-custom-class external-link">google</a> with text'
            ],
            "External link with replace_anchor=on" => [
                'Test <a href="https://google.com" class="my-custom-class">google</a> with text',
                ['replace_anchor' => '1'],
                'Test <!--noindex--><a href="https://google.com" target="_blank" title="google" rel="nofollow" class="my-custom-class external-link --set-title --href-replaced">https://google.com</a><!--/noindex--> with text'
            ],
            "External link with replace_anchor=on and replace_anchor_host=on" => [
                'Test <a href="https://google.com" class="my-custom-class">google</a> with text',
                ['replace_anchor' => '1', 'replace_anchor_host' => '1'],
                'Test <!--noindex--><a href="https://google.com" target="_blank" title="google" rel="nofollow" class="my-custom-class external-link --set-title --href-replaced">google.com</a><!--/noindex--> with text'
            ],
            "External link with usejs=on" => [
                'Test <a href="https://google.com" class="my-custom-class">google</a> with text',
                ['usejs' => '1'],
                'Test <!--noindex--><span data-href="https://google.com" data-target="_blank" data-title="google" data-rel="nofollow" class="my-custom-class external-link --set-title js-modify">google</span><!--/noindex--> with text'
            ],
        ];
    }
}
