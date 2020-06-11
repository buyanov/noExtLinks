<?php
namespace Buyanov\NoExtLinks;

/**
 * @package     Joomla.plugin
 * @subpackage  System.noextlinks
 *
 * @author      Buyanov Danila <info@saity74.ru>
 * @copyright   (C) 2012-2020 Buyanov Danila. All Rights Reserved.
 * @license     GNU/GPLv2 or later; https://www.gnu.org/licenses/gpl-2.0.html
 **/

defined('_JEXEC') or die;

use Buyanov\NoExtLinks\Support\Parser;
use Joomla\Utilities\ArrayHelper;
use Joomla\String\StringHelper;
use Joomla\Uri\Uri;

if (!class_exists('PlgSystemNoExtLinks')) {
    \JLoader::registerAlias('PlgSystemNoExtLinks', 'Buyanov\NoExtLinks\PlgSystemNoExtLinks');
    require_once(__DIR__ . '/Support/Parser.php');
    require_once(__DIR__ . '/Support/Link.php');
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
    protected $whiteList = [];

    /**
     * Array of domains for remove
     *
     * @var $removeList array
     * @since 1.0
     */
    protected $removeList = [];

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
     * Noindex wrapper flag
     *
     * @var $useRedirectPage boolean
     * @since 1.0
     */
    protected $useNoIndexWrapper;

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
        $this->useNoIndexWrapper = $this->params->get('noindex', true);

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
        $jqueryScript = '<script type="text/javascript">'
            . file_get_contents(__DIR__ . '/noextlinks.js')
            . '</script></body>';

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

        Parser::create($content, $this->params)
            ->prepare($this->whiteList, $this->removeList, [$this, 'getRedirectUri'])
            ->parse()
            ->finish();

        if ($this->params->get('usejs')) {
            $content = preg_replace('/<\/body>/i', $jqueryScript, $content);
        }

        $this->app->setBody($content);

        return true;
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
     * Method for get categories
     *
     * @return  array
     * @since   1.7.5
     */
    private function getExcludedMenuItems()
    {
        $items = ArrayHelper::toInteger(explode(',', $this->params->get('excluded_menu_items', '')), []);
        $items = array_merge($items, ArrayHelper::toInteger($this->params->get('excluded_menu', [])));

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
        $menu = $this->app->getMenu();
        if (!$menu) {
            return false;
        }

        $activeItem = $menu->getActive();
        if (!$activeItem) {
            return false;
        }

        $items = $this->getExcludedMenuItems();

        return in_array($activeItem->id, $items, false);
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
     * Create redirect page Url
     *
     * @param   string  $href  string
     *
     * @return  string
     * @since   1.7.11
     */
    public function getRedirectUri($href)
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
