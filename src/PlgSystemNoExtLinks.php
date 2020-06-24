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
use Buyanov\NoExtLinks\Support\UriList;
use Joomla\Utilities\ArrayHelper;
use Joomla\String\StringHelper;
use Joomla\Uri\Uri;
use function Buyanov\NoExtLinks\Support\base;

if (!defined('TESTS_ENV') && !class_exists(PlgSystemNoExtLinks::class)) {
    \JLoader::registerAlias('PlgSystemNoExtLinks', 'Buyanov\NoExtLinks\PlgSystemNoExtLinks');
    require_once __DIR__  . '/Support/helpers.php';
    \JLoader::registerNamespace('Buyanov\\NoExtLinks\\Support', __DIR__ . '/Support', false, false, 'psr4');
}

/**
 * Class PlgSystemNoExtLinks
 *
 */
class PlgSystemNoExtLinks extends \JPlugin
{
    /**
     * Object of Joomla! application class
     *
     * @var $app \JApplicationCms
     */
    protected $app;

    /**
     * List of excluded domains
     *
     * @var $excludedDomains UriList
     */

    protected $excludedDomains;

    /**
     * List of domains for remove
     *
     * @var $removedDomains UriList
     */
    protected $removedDomains;

    /**
     * Constructor
     *
     * @param   object  $subject  The object to observe
     * @param   array   $config   An optional associative array of configuration settings.
     *                            Recognized key values include 'name', 'group', 'params', 'language'
     *                            (this list is not meant to be comprehensive).
     *
     */
    public function __construct($subject, array $config = array())
    {
        parent::__construct($subject, $config);

        $this->excludedDomains = new UriList();
        $this->excludeSiteDomain();
        $this->excludeDomainsFromWhiteList();
        $this->createExcludedDomainsList();

        $this->removedDomains = new UriList();
        $this->createRemoveList();
    }

    /**
     * Method on Before render
     *
     * @return boolean
     */
    public function onBeforeRender(): bool
    {
        if (!$this->params->get('use_redirect_page', false)) {
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
     */
    public function onAfterRender(): bool
    {
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
            ->prepare($this->excludedDomains, $this->removedDomains, [$this, 'getRedirectUri'])
            ->parse()
            ->finish();

        if ($this->params->get('usejs')) {
            $jqueryScript = '<script type="text/javascript">'
                . file_get_contents(__DIR__ . '/noextlinks.js')
                . '</script></body>';
            $content = preg_replace('/<\/body>/i', $jqueryScript, $content);
        }

        $this->app->setBody($content);

        return true;
    }

    /**
     * Method for get categories
     *
     * @return  array
     */
    private function getExcludedCategories(): array
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
     */
    private function getExcludedMenuItems(): array
    {
        $items = ArrayHelper::toInteger(explode(',', $this->params->get('excluded_menu_items', '')), []);
        $items = array_merge($items, ArrayHelper::toInteger($this->params->get('excluded_menu', [])));

        return $items;
    }

    /**
     * Method for check active menu item
     *
     * @return boolean
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
     * @return bool
     */
    private function checkCategory(): bool
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

    private function excludeSiteDomain(): void
    {
        $theDomain = new Uri(base());
        $theDomain->setScheme('*');
        $theDomain->setPath('/*');

        $this->excludedDomains->push($theDomain);
    }

    private function excludeDomainsFromWhiteList(): void
    {
        $whiteList = $this->params->get('whitelist', array());

        if (!is_array($whiteList)) {
            $whiteList = array_unique(explode("\n", $whiteList));

            if (!empty($whiteList)) {
                foreach ($whiteList as $url) {
                    $this->excludedDomains->push($url);
                }
            }
        }
    }

    /**
     * Method for create white list
     *
     * @return  void
     */
    private function createExcludedDomainsList(): void
    {
        $exDomains = json_decode($this->params->get('excluded_domains'), true);

        if (!empty($exDomains) && is_array($exDomains)) {
            $exUris = array_map(
                static function ($scheme, $host, $path) {
                    $uri = new Uri();
                    $uri->setScheme($scheme ?: '*');
                    $uri->setHost($host);
                    $uri->setPath($path ?: '/*');

                    return $uri;
                },
                $exDomains['scheme'],
                $exDomains['host'],
                $exDomains['path']
            );

            $list = new UriList();
            $list->fromArray($exUris);
            $this->excludedDomains->merge($list);
        }
    }

    /**
     * Method for check current article
     *
     * @return boolean
     */
    private function checkArticle(): bool
    {
        $articles = explode(',', $this->params->get('excluded_articles', ''));
        $article = $this->getCurrentArticle();

        return (is_object($article) && is_array($articles) && in_array($article->id, $articles, false));
    }

    /**
     * Method get current article item
     *
     * @return object|null
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
     */
    private function createRemoveList(): void
    {
        $rmDomains = json_decode($this->params->get('removed_domains'), true);

        if ($rmDomains) {
            $rmUris = array_map(
                static function ($host) {
                    $uri = new Uri();
                    $uri->setScheme('*');
                    $uri->setHost($host);
                    $uri->setPath('/*');

                    return $uri;
                },
                $rmDomains['host']
            );

            $this->removedDomains->fromArray($rmUris);
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
