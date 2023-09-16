<?php

namespace Buyanov\NoExtLinks;

/**
 * @author      Buyanov Danila <info@saity74.ru>
 * @copyright   (C) 2012-2023 Buyanov Danila. All Rights Reserved.
 * @license     GNU/GPLv2 or later; https://www.gnu.org/licenses/gpl-2.0.html
 */
defined('_JEXEC') or exit;

use function Buyanov\NoExtLinks\Support\base;

use Buyanov\NoExtLinks\Support\Parser;
use Buyanov\NoExtLinks\Support\UriList;
use Exception;
use JLoader;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri as JUri;
use Joomla\Component\Content\Site\Model\ArticleModel;
use Joomla\Event\SubscriberInterface;
use Joomla\String\StringHelper;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;

if (!defined('TESTS_ENV') && !class_exists(PlgSystemNoExtLinks::class)) {
    JLoader::registerAlias('PlgSystemNoExtLinks', 'Buyanov\NoExtLinks\PlgSystemNoExtLinks');
    require_once __DIR__ . '/Support/helpers.php';
    JLoader::registerNamespace('Buyanov\\NoExtLinks\\Support', __DIR__ . '/Support', false, false, 'psr4');
}

/**
 * Class PlgSystemNoExtLinks.
 */
class PlgSystemNoExtLinks extends CMSPlugin implements SubscriberInterface
{
    /**
     * @var CMSApplication
     */
    protected $app;

    /**
     * List of excluded domains.
     *
     * @var UriList $excludedDomains
     */
    protected $excludedDomains;

    /**
     * List of domains for remove.
     *
     * @var UriList $removedDomains
     */
    protected $removedDomains;

    protected $allowLegacyListeners = false;

    /**
     * Constructor.
     *
     * @param object $subject The object to observe
     * @param array  $config  An optional associative array of configuration settings.
     *                        Recognized key values include 'name', 'group', 'params', 'language'
     *                        (this list is not meant to be comprehensive).
     */
    public function __construct($subject, array $config = [])
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
     * Returns an array of events this subscriber will listen to.
     *
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeRender' => 'onBeforeRender',
            'onAfterRender'  => 'onAfterRender',
        ];
    }

    /**
     * Method on Before render.
     *
     * @return bool
     */
    public function onBeforeRender(): bool
    {
        if (!$this->params->get('use_redirect_page', false)) {
            return true;
        }

        $currentItemId   = (int) $this->app->input->get('Itemid');
        $redirectItemId  = (int) $this->params->get('redirect_page');
        $redirectUrl     = $this->app->input->get('url', null, 'raw');
        $redirectTimeout = (int) $this->params->get('redirect_timeout', 5);

        if ($currentItemId && $redirectItemId && $currentItemId === $redirectItemId && $redirectUrl) {
            $doc = $this->app->getDocument();
            $doc->setMetaData('refresh', $redirectTimeout . '; ' . rawurldecode($redirectUrl), 'http-equiv');
        }

        return true;
    }

    /**
     * Method on After render.
     *
     * @throws Exception
     *
     * @return bool
     */
    public function onAfterRender(): bool
    {
        if ($this->app->isClient('administrator')) {
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
     * Create redirect page Url.
     *
     * @param string $href string
     *
     * @return string
     *
     * @since   1.7.11
     */
    public function getRedirectUri(string $href): string
    {
        $base = $this->params->get('absolutize') ? rtrim(JUri::base(), '/') : '';
        $menu = $this->app->getMenu();

        if ($menu === null) {
            return $href;
        }

        $item = $menu->getItem($this->params->get('redirect_page'));

        if ($href && $item) {
            $lang = '';

            if ($item->language !== '*' && Multilanguage::isEnabled()) {
                $lang = '&lang=' . $item->language;
            }

            return $base . Route::_('index.php?Itemid=' . $item->id . $lang . '&url=' . $href);
        }

        return $href;
    }

    /**
     * Method for get categories.
     *
     * @return array
     */
    private function getExcludedCategories(): array
    {
        $categories           = [];
        $deprecatedCategories = $this->params->get('excluded_categories');

        if ($deprecatedCategories !== null) {
            $categories = ArrayHelper::toInteger(explode(',', $deprecatedCategories));
        }

        return array_merge(
            $categories,
            ArrayHelper::toInteger($this->params->get('excluded_category_list', []))
        );
    }

    /**
     * Method for get categories.
     *
     * @return array
     */
    private function getExcludedMenuItems(): array
    {
        $items           = [];
        $deprecatedItems = $this->params->get('excluded_menu_items');

        if ($deprecatedItems !== null) {
            $items = ArrayHelper::toInteger(explode(',', $deprecatedItems), []);
        }

        return array_merge(
            $items,
            ArrayHelper::toInteger($this->params->get('excluded_menu', []))
        );
    }

    /**
     * Method for check active menu item.
     *
     * @return bool
     */
    private function checkMenuItem(): bool
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
     * Method for check current category.
     *
     * @throws Exception
     *
     * @return bool
     */
    private function checkCategory(): bool
    {
        $result = false;

        $categories = $this->getExcludedCategories();
        $extension  = $this->app->getInput()->get('option');
        $view       = $this->app->getInput()->get('view');
        $id         = $this->app->getInput()->get('id');

        if (!empty($categories) && $extension === 'com_content') {
            if (($view === 'blog' || $view === 'category') && in_array($id, $categories, false)) {
                return true;
            }

            $currentArticle = $this->getCurrentArticle();
            $result         = $currentArticle && in_array($currentArticle->catid, $categories, false);
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
        $whiteList = $this->params->get('whitelist', []);

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
     * Method for create white list.
     *
     * @return void
     */
    private function createExcludedDomainsList(): void
    {
        $excludedDomains = $this->params->get('excluded_domains');

        if ($excludedDomains !== null) {
            $exDomains = json_decode($excludedDomains, true);
        }

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
     * Method for check current article.
     *
     * @throws Exception
     *
     * @return bool
     */
    private function checkArticle(): bool
    {
        $articles = explode(',', $this->params->get('excluded_articles', ''));
        $article  = $this->getCurrentArticle();

        return is_object($article) && is_array($articles) && in_array($article->id, $articles, false);
    }

    /**
     * Method get current article item.
     *
     * @throws Exception
     *
     * @return object|null
     */
    private function getCurrentArticle(): ?object
    {
        $component = $this->app->getInput()->get('option');
        $view      = $this->app->getInput()->get('view');

        if (($component !== 'com_content')
            || $view !== 'article'
            || !$this->app->getInput()->get('id')) {
            return null;
        }

        $articleModel = $this->app
            ->bootComponent($component)
            ->getMVCFactory()
            ->createModel($view);

        if ($articleModel instanceof ArticleModel) {
            return $articleModel->getItem();
        }

        return null;
    }

    /**
     * Method for create remove domains list.
     *
     * @return void
     */
    private function createRemoveList(): void
    {
        $rmDomains      = null;
        $removedDomains = $this->params->get('excluded_domains');

        if ($removedDomains !== null) {
            $rmDomains = json_decode($removedDomains, true);
        }

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
}
