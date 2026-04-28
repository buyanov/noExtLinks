<?php

namespace Buyanov\NoExtLinks\Support;

use Joomla\Registry\Registry;
use Joomla\Uri\Uri;

class Parser
{
    private const OPEN_ANCHOR_PATTERN = '<a';

    protected $blocks = [];

    protected $content;

    protected $options;

    /**
     * @var UriList $whiteList
     */
    protected $whiteList;

    /**
     * @var UriList $removeList
     */
    protected $removeList;

    protected $getRedirectUri;

    public function __construct(string &$content, $options = [])
    {
        $this->content = &$content;
        $this->options = new Registry($options);
    }

    public static function create(string &$content, $options = []): Parser
    {
        return new self($content, $options);
    }

    public function prepare(UriList $whiteList, UriList $removeList, ?callable $fn = null): Parser
    {
        $this->whiteList = $whiteList;
        $this->removeList = $removeList;
        $this->getRedirectUri = $fn;

        $this->content = preg_replace_callback(
            '#<!-- extlinks -->(.*?)<!-- \/extlinks -->#s',
            [$this, 'excludeBlocks'],
            $this->content
        );

        return $this;
    }

    public function parse(): Parser
    {
        $content = $this->content;
        $offset = 0;
        $result = '';

        while (($openStart = stripos($content, self::OPEN_ANCHOR_PATTERN, $offset)) !== false) {
            if (!$this->isAnchorTagStart($content, $openStart)) {
                $result .= substr($content, $offset, $openStart + 2 - $offset);
                $offset = $openStart + 2;
                continue;
            }

            $openEnd = $this->findTagEnd($content, $openStart + 2);

            if ($openEnd === null) {
                break;
            }

            $closeStart = stripos($content, '</a>', $openEnd + 1);

            if ($closeStart === false) {
                break;
            }

            $text = substr($content, $openStart, $closeStart + 4 - $openStart);
            $args = substr($content, $openStart + 2, $openEnd - $openStart - 2);
            $anchor = substr($content, $openEnd + 1, $closeStart - $openEnd - 1);
            $attributes = self::parseAttributes($args);

            if (!isset($attributes['href']) || !$this->isSupportedHref($attributes['href'])) {
                $result .= substr($content, $offset, $closeStart + 4 - $offset);
                $offset = $closeStart + 4;
                continue;
            }

            $result .= substr($content, $offset, $openStart - $offset);
            $result .= $this->replaceAnchor($text, $attributes['href'], $anchor, $attributes);
            $offset = $closeStart + 4;
        }

        $result .= substr($content, $offset);
        $this->content = $result;

        return $this;
    }

    public function finish(): void
    {
        if (!empty($this->blocks)) {
            $this->blocks = array_reverse($this->blocks);
            $this->content = preg_replace_callback(
                '/<!-- noExternalLinks-White-Block -->/i',
                [$this, 'includeBlocks'],
                $this->content
            );
        }
    }

    /**
     * Method for replace white blocks
     *
     * @param   array  $matches  Array of blocks
     * @return  string
     */
    private function excludeBlocks($matches): string
    {
        $this->blocks[] = $matches[1];

        return '<!-- noExternalLinks-White-Block -->';
    }

    /**
     * Method for return excluded blocks into content
     *
     * @return  string
     */
    private function includeBlocks(): string
    {
        return '<!-- extlinks -->' . array_pop($this->blocks) . '<!-- /extlinks -->';
    }

    private function isAnchorTagStart(string $content, int $position): bool
    {
        $previous = $content[$position - 1] ?? '';
        $next = $content[$position + 2] ?? '';

        return $previous !== '<' && ($next === '' || ctype_space($next) || $next === '>');
    }

    private function findTagEnd(string $content, int $offset): ?int
    {
        $quote = null;
        $length = strlen($content);

        for ($i = $offset; $i < $length; $i++) {
            $char = $content[$i];

            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '>') {
                return $i;
            }
        }

        return null;
    }

    private function isSupportedHref(string $href): bool
    {
        return $href !== '' && (preg_match('/^[\w\/\.#]/u', $href) === 1);
    }

    protected function replaceAnchor(string $text, string $href, string $anchor, array $args)
    {
        // If anchor for element on same page - ignore it
        if (str_starts_with($href, '#')) {
            return $text;
        }

        $uri = new Uri($href);

        if ($this->isRelativeUri($uri)) {
            if ($this->options->get('absolutize')) {
                $newHref = base() . (str_starts_with($href, '/')
                    ? ltrim($href, '/')
                    : $href);
                $link = Link::create()->setAnchor($anchor)->setArgs($args);
                $link->href = $newHref;

                return $link;
            }

            return $text;
        }

        // Filter "tel:", "whatsapp://send...", "skype:" etc.
        if ($this->shouldSkipUri($uri)) {
            return $text;
        }

        if ($this->removeList->exists($uri)) {
            return '';
        }

        if ($this->whiteList->exists($uri)) {
            return $text;
        }

        $text = $this->link($anchor, $args);

        if (!$this->options->get('use_redirect_page', false)
            && $this->options->get('noindex', true)) {
            $text = '<!--noindex-->' . $text . '<!--/noindex-->';
        }

        return $text;
    }

    private function link(string $anchor, array $args): string
    {
        $link = Link::create()
            ->setAnchor($anchor)
            ->setArgs($args)
            ->addClass('external-link');

        if ($this->options->get('blank') !== '0') {
            $link->target = $this->options->get('blank');
        }

        $anchorText = trim(strip_tags($anchor));

        if (!isset($args['title']) && $anchorText && $this->options->get('settitle')) {
            $link->addClass('--set-title');
            $link->title = $anchorText;
        }

        if ($anchorText === $anchor && $this->options->get('replace_anchor')) {
            $link->addClass('--href-replaced');
            $link->setAnchor($args['href']);

            if ($this->options->get('replace_anchor_host')) {
                $link->setAnchor((new Uri($args['href']))->getHost());
            }
        }

        if ($this->options->get('usejs')) {
            $link->addClass('js-modify');
            $link->setTag('span');
        }

        $args = array_filter($args);

        if ($this->options->get('use_redirect_page', false)) {
            $link->addClass('--internal-redirect');

            if (is_callable($this->getRedirectUri)) {
                $link->href = call_user_func($this->getRedirectUri, $args['href']);
            }

            return (string) $link;
        }

        if ($this->options->get('nofollow') !== '0') {
            $link->rel = $this->options->get('nofollow');
        }

        return (string) $link;
    }

    private function isRelativeUri($uri): bool
    {
        return (!$uri->toString(['scheme', 'host', 'port']) && $uri->toString(['path', 'query', 'fragment']));
    }

    /**
     * Copy from JUtility
     * Method to extract key/value pairs out of a string with XML style attributes
     *
     * @param   string  $string  String containing XML style attributes
     * @return  array  Key/Value pairs for the attributes
     */
    public static function parseAttributes($string): array
    {
        $attributes = [];
        $length = strlen($string);
        $offset = 0;

        while ($offset < $length) {
            while ($offset < $length && ctype_space($string[$offset])) {
                $offset++;
            }

            if ($offset >= $length) {
                break;
            }

            $nameStart = $offset;

            while ($offset < $length && preg_match('/[\w:-]/', $string[$offset]) === 1) {
                $offset++;
            }

            if ($nameStart === $offset) {
                $offset++;
                continue;
            }

            $name = strtolower(substr($string, $nameStart, $offset - $nameStart));

            while ($offset < $length && ctype_space($string[$offset])) {
                $offset++;
            }

            if ($offset >= $length || $string[$offset] !== '=') {
                $attributes[$name] = $name;
                continue;
            }

            $offset++;

            while ($offset < $length && ctype_space($string[$offset])) {
                $offset++;
            }

            if ($offset >= $length) {
                $attributes[$name] = '';
                break;
            }

            $quote = $string[$offset];

            if ($quote === '"' || $quote === "'") {
                $offset++;
                $valueStart = $offset;

                while ($offset < $length && $string[$offset] !== $quote) {
                    $offset++;
                }

                $attributes[$name] = substr($string, $valueStart, $offset - $valueStart);

                if ($offset < $length) {
                    $offset++;
                }

                continue;
            }

            $valueStart = $offset;

            while ($offset < $length && !ctype_space($string[$offset]) && $string[$offset] !== '>') {
                $offset++;
            }

            $attributes[$name] = substr($string, $valueStart, $offset - $valueStart);
        }

        return $attributes;
    }

    protected function shouldSkipUri(Uri $uri): bool
    {
        $scheme = strtolower((string) $uri->getScheme());

        return ($uri->getHost() && !in_array($scheme, ['http', 'https'], true))
            || (!$uri->getHost() && !in_array($scheme, ['http', 'https'], true));
    }
}
