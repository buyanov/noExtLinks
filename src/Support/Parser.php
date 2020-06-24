<?php

namespace Buyanov\NoExtLinks\Support;

use Joomla\Registry\Registry;
use Joomla\String\StringHelper;
use Joomla\Uri\Uri;
use function Buyanov\NoExtLinks\Support\base;


class Parser
{

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
        return new static($content, $options);
    }

    public function prepare(UriList $whiteList, UriList $removeList, callable $fn = null): Parser
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
        /* phpcs:ignore */
        $regex = '/<a(?:\s*?)(?P<args>(?=(?:[^>=]|=")*?\shref="(?=[\w]|[\/\.#])(?P<href>[^"]*)")[^<>]*)>(?P<anchor>.*?)<\/a>/ius';
        $this->content = preg_replace_callback($regex, [$this, 'replace'], $this->content);

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

    /**
     * Method for replace links
     *
     * @param   array  $matches  Array with matched links
     * @return  mixed|string
     */
    protected function replace($matches)
    {
        [$text, $pureArgs, $href, $anchor] = $matches;

        // If anchor for element on same page - ignore it
        if (StringHelper::strpos($href, '#') === 0) {
            return $text;
        }

        $args = static::parseAttributes($pureArgs);
        $uri = new Uri($href);

        if ($this->isRelativeUri($uri)) {
            if ($this->options->get('absolutize')) {
                $newHref = base() . (StringHelper::strpos($href, '/') === 0
                    ? ltrim($href, '/')
                    : $href);
                $link = Link::create()->setAnchor($anchor)->setArgs($args);
                $link->href = $newHref;

                return $link;
            }

            return $text;
        }

        // Filter "tel:", "whatsup://send...", "skype:" etc
        if ($this->isHttpUri($uri)) {
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
        $attr = [];
        $retarray = [];

        // Let's grab all the key/value pairs using a regular expression
        preg_match_all('/([\w:-]+)[\s]?=[\s]?"([^"]*)"/i', $string, $attr);

        if (is_array($attr)) {
            $numPairs = count($attr[1]);

            for ($i = 0; $i < $numPairs; $i++) {
                $retarray[$attr[1][$i]] = $attr[2][$i];
            }
        }

        return $retarray;
    }

    /**
     * @param Uri $uri
     * @return bool
     */
    protected function isHttpUri(Uri $uri): bool
    {
        return ($uri->getHost() && !in_array(strtolower($uri->getScheme()), ['http', 'https']))
            || (!$uri->getHost() && !in_array(strtolower($uri->getScheme()), ['http', 'https']));
    }
}
