<?php
namespace buyanov\noextlinks;

/**
 * @package     Joomla.plugin
 * @subpackage  System.noextlinks
 *
 * @author      Buyanov Danila <info@saity74.ru>
 * @copyright   (C) 2012-2019 Saity74 LLC. All Rights Reserved.
 * @license     GNU/GPLv2 or later; https://www.gnu.org/licenses/gpl-2.0.html
 **/

defined('_JEXEC') or die;

use Joomla\Utilities\ArrayHelper;
use Joomla\String\StringHelper;
use Joomla\Uri\Uri;

if (!class_exists('PlgSystemNoExtLinks')) {
    \JLoader::registerAlias('PlgSystemNoExtLinks', 'buyanov\noextlinks\PlgSystemNoExtLinks');
}

/**
 * Class PlgSystemNoExtLinks
 *
 * @since 3.2
 */
class PlgSystemNoExtLinks extends \JPlugin
{
    /**
     * Object of Joomla! application class
     *
     * @var $app \JApplicationCms
     * @since 1.0
     */
    protected $app;

    /**
     * Array of excluded domains
     *
     * @var $whiteList array
     * @since 1.0
     */
    protected $whiteList = array();

    /**
     * Array of domains for remove
     *
     * @var $removeList array
     * @since 1.0
     */
    protected $removeList = array();

    /**
     * Array of excluded html blocks
     *
     * @var $blocks array
     * @since 1.0
     */
    protected static $blocks;

    /**
     * Redirect page flag
     *
     * @var $useRedirectPage boolean
     * @since 1.0
     */
    protected $useRedirectPage;

    /**
     * Constructor
     *
     * @param   object  $subject  The object to observe
     * @param   array   $config   An optional associative array of configuration settings.
     *                            Recognized key values include 'name', 'group', 'params', 'language'
     *                            (this list is not meant to be comprehensive).
     *
     * @since   1.7.5
     */
    public function __construct($subject, array $config = array())
    {
        parent::__construct($subject, $config);

        $this->useRedirectPage = $this->params->get('use_redirect_page', false);

        $this->createWhiteList();
        $this->createRemoveList();
    }

    /**
     * Method on Before render
     *
     * @return boolean
     * @since 1.7.11
     */
    public function onBeforeRender()
    {
        if (!$this->useRedirectPage) {
            return true;
        }

        $currentItemId      = (int) $this->app->input->get('Itemid');
        $redirectItemId     = (int) $this->params->get('redirect_page');
        $redirectUrl        = $this->app->input->get('url', null, 'raw');
        $redirectTimeout    = (int) $this->params->get('redirect_timeout', 5);

        if ($currentItemId && $redirectItemId && $currentItemId === $redirectItemId && $redirectUrl) {
            $doc = $this->app->getDocument();
            $doc->setMetaData('refresh', $redirectTimeout . '; ' . rawurldecode($redirectUrl), 'http-equiv');
        }

        return true;
    }

    /**
     * Method on After render
     *
     * @return boolean
     * @since 1.0
     */
    public function onAfterRender()
    {
        $jqueryScript = <<< HTML
<script type="text/javascript">
    jQuery(document).ready(function() {
        jQuery("span.external-link").each(function(i, el) {
            var data = jQuery(el).data();
            jQuery(el).wrap(jQuery("<a>").attr({
                "href" : data.href, 
                "title" : data.title, 
                "target" : data.target,
                "rel" : data.rel
            }).addClass(jQuery(el).prop('class')))
        })
    })
</script></body>
HTML;

        if ($this->app->isAdmin()) {
            return true;
        }

        $content = $this->app->getBody();

        if (StringHelper::strpos($content, '</a>') === false) {
            return true;
        }

        if ($this->checkArticle() || $this->checkMenuItem() || $this->checkCategory()) {
            return true;
        }

        $content = preg_replace_callback(
            '#<!-- extlinks -->(.*?)<!-- \/extlinks -->#s',
            'static::excludeBlocks',
            $content
        );

        /* phpcs:ignore */
        $regex = '/<a(?:\s*?)(?P<args>(?=(?:[^>=]|=")*?\shref="(?=[\w]|[\/\.#])(?P<href>[^"]*)")[^<>]*)>(?P<anchor>.*?)<\/a>/ius';
        $content = preg_replace_callback($regex, array($this, 'replace'), $content);

        if (is_array(static::$blocks) && !empty(static::$blocks)) {
            static::$blocks = array_reverse(static::$blocks);
            $content = preg_replace_callback(
                '/<!-- noExternalLinks-White-Block -->/i',
                'static::includeBlocks',
                $content
            );
        }

        if ($this->params->get('usejs')) {
            $content = preg_replace('/<\/body>/i', $jqueryScript, $content);
        }

        $this->app->setBody($content);

        return true;
    }

    /**
     * Method for replace links
     *
     * @param   array  $matches  Array with matched links
     *
     * @return  mixed|string
     * @since 1.0
     */
    protected function replace($matches)
    {
        static::checkMatch($matches);

        $text = $matches[0];

        if (stripos($matches['href'], '#') === 0) {
            return $text;
        }

        $anchor = $matches['anchor'];
        $base = \JUri::root();
        $href = $matches['href'];
        $args = \JUtility::parseAttributes($matches['args']);
        $uri = new Uri($href);

        if (empty($anchor) || (!$uri->getHost() && $uri->getScheme())) {
            return $text;
        }

        if ($this->isRelativeUri($uri)) {
            if ($this->params->get('absolutize')) {
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

        if ($this->params->get('noindex') && !$this->useRedirectPage) {
            $text = '<!--noindex-->' . $text . '<!--/noindex-->';
        }

        return $text;
    }

    /**
     * Method for replace white blocks
     *
     * @param   array  $matches  Array of blocks
     *
     * @return  string
     * @since 1.0
     */
    protected static function excludeBlocks($matches)
    {
        static::$blocks[] = $matches[1];

        return '<!-- noExternalLinks-White-Block -->';
    }

    /**
     * Method for return excluded blocks into content
     *
     * @return  string
     * @since 1.0
     */
    protected static function includeBlocks()
    {
        $block = array_pop(static::$blocks);

        return '<!-- extlinks -->' . $block . '<!-- /extlinks -->';
    }

    /**
     * Method for create Uri string from parts
     *
     * @param   string  $host    Uri host
     * @param   string  $scheme  Uri scheme
     * @param   string  $path    Uri path
     *
     * @return  array
     * @since 1.0
     */
    protected static function createUri($host, $scheme = null, $path = null)
    {
        $uri = new Uri;
        $uri->setScheme($scheme ?: '*');
        $uri->setHost($host);
        $uri->setPath($path ?: '/*');

        return array($uri->toString(array('host')) => $uri);
    }

    /**
     * Method for get categories
     *
     * @return  array
     * @since   1.7.5
     */
    private function getExcludedCategories()
    {
        $categories = ArrayHelper::toInteger(explode(',', $this->params->get('excluded_categories', '')));
        $categories = array_merge(
            $categories,
            ArrayHelper::toInteger($this->params->get('excluded_category_list', array()))
        );

        return $categories;
    }

    /**
     * Check the buffer.
     *
     * @param   array  $match  Buffer to be checked.
     *
     * @return  void
     * @since   1.7.5
     */
    private function checkMatch($match)
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

    /**
     * Check the URI.
     *
     * @param   Uri  $uri  Uri object to be checked.
     *
     * @return  boolean
     * @since   1.7.5
     */
    private function isRelativeUri($uri)
    {
        return (!$uri->toString(array('scheme', 'host', 'port')) && $uri->toString(array('path', 'query', 'fragment')));
    }

    /**
     * Check the URI.
     *
     * @param   Uri  $uri  Uri object to be checked.
     *
     * @return  boolean
     * @since   1.7.5
     */
    private function isExcludedUri($uri)
    {
        $isExcluded = false;
        $domain = $uri->toString(array('host', 'port'));

        if (!empty($domain)) {
            foreach ($this->whiteList as $eUri) {
                $regex = [];

                if ($eUri->getScheme() === '*') {
                    $regex[] = 'http[s]?\://';
                } else {
                    $regex[] = $eUri->getScheme() . '\://';
                }

                if ($host = $eUri->toString(array('host', 'port'))) {
                    $regex[] = str_replace('\*', '[\w\-]+', $host);
                }

                if ($path = $eUri->getPath()) {
                    $regex[] = str_replace('/\*', '(\/[\w\-\~\:\.\/]*|)', $path);
                }

                $regex = '~^' . implode('', $regex) . '$~iU';

                if (preg_match($regex, $uri->toString(array('scheme', 'user', 'pass', 'host', 'port', 'path')))) {
                    $isExcluded = true;
                }
            }
        }

        return $isExcluded;
    }

    /**
     * Method for get categories
     *
     * @return  array
     * @since   1.7.5
     */
    private function getExcludedMenuItems()
    {
        $items = ArrayHelper::toInteger(explode(',', $this->params->get('excluded_menu_items', '')), array());
        $items = array_merge($items, ArrayHelper::toInteger($this->params->get('excluded_menu', array())));

        return $items;
    }

    /**
     * Method for check active menu item
     *
     * @return boolean
     * @since  1.7.5
     */
    private function checkMenuItem()
    {
        $result = false;

        if (($menu = $this->app->getMenu()) && ($activeItem = $menu->getActive())) {
            $items = $this->getExcludedMenuItems();
            $result = !empty($items) && is_array($items) && in_array($activeItem->id, $items, false);
        }

        return $result;
    }

    /**
     * Method for check current category
     *
     * @return boolean
     * @since  1.7.5
     */
    private function checkCategory()
    {
        $result = false;
        $categories = $this->getExcludedCategories();
        $extension = $this->app->input->request->get('option');
        $view = $this->app->input->request->get('view');
        $id = $this->app->input->request->get('id');

        if (!empty($categories) && $extension === 'com_content') {
            if (($view === 'blog' || $view === 'category') && in_array($id, $categories, false)) {
                return true;
            }

            $currentArticle = $this->getCurrentArticle();
            $result = $currentArticle && in_array($currentArticle->catid, $categories, false);
        }

        return $result;
    }

    /**
     * Method for create white list
     *
     * @return  void
     * @since 1.7.5
     */
    private function createWhiteList()
    {
        $theDomain = new Uri(\JUri::getInstance());
        $theDomain->setScheme('*');
        $theDomain->setPath('/*');
        $this->whiteList += array($theDomain->toString(array('host', 'port')) => $theDomain);

        $whiteList = $this->params->get('whitelist', array());

        if (!is_array($whiteList)) {
            $whiteList = array_unique(explode("\n", $whiteList));

            if (!empty($whiteList)) {
                foreach ($whiteList as $url) {
                    if (trim($url)) {
                        $uri = new Uri(trim($url));
                        $this->whiteList += static::createUri($uri->getHost(), $uri->getScheme(), $uri->getPath());
                    }
                }
            }
        }

        $exDomains = json_decode($this->params->get('excluded_domains'), true);

        if (!empty($exDomains) && is_array($exDomains)) {
            $domains = array_map('static::createUri', $exDomains['host'], $exDomains['scheme'], $exDomains['path']);
        }

        if (!empty($domains) && is_array($domains)) {
            $this->whiteList = array_merge($this->whiteList, ...$domains);
        }
    }

    /**
     * Method for check current article
     *
     * @return boolean
     * @since  1.7.5
     */
    private function checkArticle()
    {
        $articles = explode(',', $this->params->get('excluded_articles', ''));
        $article = $this->getCurrentArticle();

        return (is_object($article) && is_array($articles) && in_array($article->id, $articles, false));
    }

    /**
     * Method get current article item
     *
     * @return object|null
     * @since  1.7.5
     */
    private function getCurrentArticle()
    {
        if (($this->app->input->get('option') !== 'com_content')
            || ($this->app->input->get('view') !== 'article') || !$this->app->input->get('id')) {
            return null;
        }

        if (!\JLoader::import('models.article', JPATH_COMPONENT_SITE)) {
            return null;
        }

        $articleModel = new \ContentModelArticle();
        $currentArticle = $articleModel->getItem();

        return $currentArticle ?: null;
    }

    /**
     * Method for generate link
     *
     * @param   string  $anchor  Link anchor
     * @param   array   $args    Array with link attributes
     *
     * @return  string
     * @since   1.7.5
     */
    private function link($anchor, $args)
    {
        if (isset($args['class'])) {
            $args['class'] = explode(' ', $args['class']);
        }

        $args['class'][] = 'external-link';

        $args['target'] = $this->params->get('blank');
        $anchorText = trim(strip_tags($anchor));

        if ($this->params->get('settitle') && !isset($args['title']) && $anchorText) {
            $args['title'] = $anchorText;
            $args['class'][] = '--set-title';
        }

        if ($this->params->get('replace_anchor') && $anchorText == $anchor) {
            if ($this->params->get('replace_anchor_host')) {
                $uri = new Uri($args['href']);
                $anchor = $uri->getHost();
            } else {
                $anchor = $args['href'];
            }

            $args['class'][] = '--href-replaced';
        }

        if ($useJS = $this->params->get('usejs')) {
            $args['class'][] = 'js-modify';
        }

        $args = array_filter($args);

        if ($this->useRedirectPage) {
            $args['class'][] = '--internal-redirect';
            $args['href'] = $this->getRedirectUri($args['href']);
        } else {
            $args['rel'] = $this->params->get('nofollow');
        }

        $props = '';

        foreach ($args as $key => $value) {
            $v = is_array($value) ? implode(' ', $value) : $value;
            if ($useJS && $key !== 'class') {
                $key = 'data-' . $key;
            }
            $props .= $key . '="' . $v . '" ';
        }

        $tagName = $useJS ? 'span' : 'a';

        return '<' . $tagName . ' ' . $props . '>' . $anchor . '</' . $tagName . '>';
    }

    /**
     * Method for create remove domains list
     *
     * @return  void
     * @since 1.7.10
     */
    private function createRemoveList()
    {
        $this->removeList = null;
        $rmDomains = json_decode($this->params->get('removed_domains'), true);

        if (!empty($rmDomains) && is_array($rmDomains) && isset($rmDomains['host']) && !empty($rmDomains['host'])) {
            $this->removeList = call_user_func_array("array_merge", array_map("static::createUri", $rmDomains['host']));
        }
    }

    /**
     * Check the URI for remove.
     *
     * @param   Uri  $uri  Uri object to be checked.
     *
     * @return  boolean
     * @since   1.7.10
     */
    private function isRemovedUri($uri)
    {
        $domain = $uri->toString(array('host'));

        return !empty($domain) && !empty($this->removeList) && array_key_exists($domain, $this->removeList);
    }

    /**
     * Create redirect page Url
     *
     * @param   string  $href  string
     *
     * @return  string
     * @since   1.7.11
     */
    private function getRedirectUri($href)
    {
        $base  = $this->params->get('absolutize') ? rtrim(\JUri::base(), '/') : '';
        $item  = $this->app->getMenu()->getItem($this->params->get('redirect_page'));

        if ($href && $item) {
            $lang = '';

            if ($item->language !== '*' && \JLanguageMultilang::isEnabled()) {
                $lang = '&lang=' . $item->language;
            }

            return $base . \JRoute::_('index.php?Itemid=' . $item->id . $lang . '&url=' . $href, true);
        }

        return $href;
    }
}
