<?php

namespace Buyanov\NoExtLinks\Support;

use Joomla\Registry\Registry;
use Joomla\String\StringHelper;
use Joomla\Uri\Uri;

class Parser
{

    protected static $blocks = [];

    protected $content;

    protected $options;

    protected $whiteList = [];

    protected $removeList = [];

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

    public function prepare(array $whiteList = [], array $removeList = [], callable $fn = null): Parser
    {
        $this->whiteList = $whiteList;
        $this->removeList = $removeList;
        $this->getRedirectUri = $fn;

        $this->content = preg_replace_callback(
            '#<!-- extlinks -->(.*?)<!-- \/extlinks -->#s',
            'static::excludeBlocks',
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
        if (!empty(static::$blocks)) {
            static::$blocks = array_reverse(static::$blocks);
            $this->content = preg_replace_callback(
                '/<!-- noExternalLinks-White-Block -->/i',
                'static::includeBlocks',
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
    private static function excludeBlocks($matches)
    {
        static::$blocks[] = $matches[1];

        return '<!-- noExternalLinks-White-Block -->';
    }

    /**
     * Method for return excluded blocks into content
     *
     * @return  string
     */
    private static function includeBlocks()
    {
        return '<!-- extlinks -->' . array_pop(static::$blocks) . '<!-- /extlinks -->';
    }

    /**
     * Method for replace links
     *
     * @param   array  $matches  Array with matched links
     * @return  mixed|string
     */
    protected function replace($matches)
    {
        static::checkMatch($matches);

        $text = $matches[0];

        if (StringHelper::strpos($matches['href'], '#') === 0) {
            return $text;
        }

        $anchor = $matches['anchor'];
        $base = static::base();
        $href = $matches['href'];
        $args = static::parseAttributes($matches['args']);
        $uri = new Uri($href);

        // Filter "tel:", "whatsup::/send...", "skype:" etc
        if ($this->isHttpUri($uri)) {
            return $text;
        }

        if ($this->isRelativeUri($uri)) {
            if ($this->options->get('absolutize')) {
                unset($args['href']);
                $text = \JHTML::link(rtrim($base, '/') . $href, $anchor, $args);
            }

            return $text;
        }

        if ($this->isRemovedUri($uri)) {
            return '';
        }

        if ($this->isExcludedUri($uri)) {
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
            ->setArgs($args);

        if (isset($args['class'])) {
            $class = explode(' ', $args['class']);
            $link->addArgs(['class' => $class]);
        }

        $link->addClass('external-link');

        $link->target = $this->options->get('blank');
        $anchorText = trim(strip_tags($anchor));

        if (!isset($args['title']) && $anchorText && $this->options->get('settitle')) {
            $link->title = $anchorText;
            $link->addClass('--set-title');
        }

        if ($anchorText === $anchor && $this->options->get('replace_anchor')) {
            if ($this->options->get('replace_anchor_host')) {
                $uri = new Uri($args['href']);
                $link->setAnchor($uri->getHost());
            } else {
                $link->setAnchor($args['href']);
            }

            $link->addClass('--href-replaced');
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

        if ($this->options->get('nofollow') === 'nofollow') {
            $link->rel = 'nofollow';
        }

        return (string) $link;
    }

    /**
     * Check the buffer.
     *
     * @param   array  $match  Buffer to be checked.
     * @return  void
     */
    private static function checkMatch($match): void
    {
        if ($match === null) {
            switch (preg_last_error()) {
                case PREG_BACKTRACK_LIMIT_ERROR:
                    $message = 'PHP regular expression limit reached (pcre.backtrack_limit)';
                    break;
                case PREG_RECURSION_LIMIT_ERROR:
                    $message = 'PHP regular expression limit reached (pcre.recursion_limit)';
                    break;
                case PREG_BAD_UTF8_ERROR:
                    $message = 'Bad UTF8 passed to PCRE function';
                    break;
                default:
                    $message = 'Unknown PCRE error calling PCRE function';
            }

            throw new RuntimeException($message);
        }
    }

    private function isRelativeUri($uri): bool
    {
        return (!$uri->toString(['scheme', 'host', 'port']) && $uri->toString(['path', 'query', 'fragment']));
    }

    /**
     * Check the URI.
     *
     * @param   Uri  $uri  Uri object to be checked.
     * @return  boolean
     */
    private function isExcludedUri($uri): bool
    {
        $domain = $uri->toString(['host', 'port']);

        if (!empty($domain)) {

            foreach ($this->whiteList as $eUri) {
                $regex = [];

                if ($eUri->getScheme() === '*') {
                    $regex[] = 'http[s]?\://';
                } else {
                    $regex[] = $eUri->getScheme() . '\://';
                }

                if ($host = $eUri->toString(['host', 'port'])) {
                    $regex[] = str_replace('\*', '[\w\-]+', $host);
                }

                if ($path = $eUri->getPath()) {
                    $regex[] = str_replace('/\*', '(\/[\w\-\~\:\.\/]*|)', $path);
                }

                $rx = '~^' . implode('', $regex) . '$~iU';

                if (preg_match($rx, $uri->toString(['scheme', 'user', 'pass', 'host', 'port', 'path']))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check the URI for remove.
     *
     * @param   Uri  $uri  Uri object to be checked.
     * @return  boolean
     */
    private function isRemovedUri($uri)
    {
        $domain = $uri->toString(array('host'));

        return !empty($domain) && !empty($this->removeList) && array_key_exists($domain, $this->removeList);
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
        $attr = array();
        $retarray = array();

        // Let's grab all the key/value pairs using a regular expression
        preg_match_all('/([\w:-]+)[\s]?=[\s]?"([^"]*)"/i', $string, $attr);

        if (is_array($attr))
        {
            $numPairs = count($attr[1]);

            for ($i = 0; $i < $numPairs; $i++)
            {
                $retarray[$attr[1][$i]] = $attr[2][$i];
            }
        }

        return $retarray;
    }

    public static function base(): string
    {
        $host = $_SERVER['HTTP_HOST'];
        $scheme = $_SERVER['REQUEST_SCHEME'];

        if (strpos(php_sapi_name(), 'cgi') !== false
            && !ini_get('cgi.fix_pathinfo')
            && !empty($_SERVER['REQUEST_URI'])) {
            $script_name = $_SERVER['PHP_SELF'];
        } else {
            $script_name = $_SERVER['SCRIPT_NAME'];
        }

        return $scheme . '://' . $host . rtrim(dirname($script_name), '/\\');
    }

    /**
     * @param Uri $uri
     * @return bool
     */
    protected function isHttpUri(Uri $uri): bool
    {
        return !$uri->getHost() || !in_array(strtolower($uri->getScheme()), ['http', 'https']);
    }
}