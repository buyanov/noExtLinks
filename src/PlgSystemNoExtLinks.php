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
use Joomla\CMS\Event\Application\AfterRenderEvent;
use Joomla\CMS\Event\Application\BeforeRenderEvent;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri as CmsUri;
use Joomla\Uri\Uri;
use Joomla\Event\SubscriberInterface;
use function Buyanov\NoExtLinks\Support\base;

if (!defined('TESTS_ENV')) {
    require_once __DIR__ . '/Support/helpers.php';

    spl_autoload_register(static function (string $class): void {
        $prefix = __NAMESPACE__ . '\\Support\\';

        if (strpos($class, $prefix) !== 0) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $path = __DIR__ . '/Support/' . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_file($path)) {
            require_once $path;
        }
    });
}

/**
 * Class PlgSystemNoExtLinks
 *
 */
class PlgSystemNoExtLinks extends CMSPlugin implements SubscriberInterface
{
    /**
     * List of excluded domains
     *
     * @var UriList
     */

    protected $excludedDomains;

    /**
     * List of domains for remove
     *
     * @var UriList
     */
    protected $removedDomains;

    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeRender' => 'onBeforeRender',
            'onAfterRender' => 'onAfterRender',
        ];
    }

    /**
     * Constructor
     *
     * @param   array   $config   An optional associative array of configuration settings.
     *                            Recognized key values include 'name', 'group', 'params', 'language'
     *                            (this list is not meant to be comprehensive).
     *
     */
    public function __construct(array $config = array())
    {
        parent::__construct($config);

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
    public function onBeforeRender(?BeforeRenderEvent $event = null): bool
    {
        if (!$this->params->get('use_redirect_page', false)) {
            return true;
        }

        $app                = $this->getApplication();
        $currentItemId      = (int) $app->input->get('Itemid');
        $redirectItemId     = (int) $this->params->get('redirect_page');
        $redirectUrl        = $app->input->get('url', null, 'raw');
        $redirectTimeout    = (int) $this->params->get('redirect_timeout', 5);

        if ($currentItemId && $redirectItemId && $currentItemId === $redirectItemId && $redirectUrl) {
            $doc = $app->getDocument();
            $doc->setMetaData('refresh', $redirectTimeout . '; ' . rawurldecode($redirectUrl), 'http-equiv');
        }

        return true;
    }

    /**
     * Method on After render
     *
     * @return boolean
     */
    public function onAfterRender(?AfterRenderEvent $event = null): bool
    {
        if ($this->isAdminClient()) {
            return true;
        }

        $app = $this->getApplication();
        $content = $app->getBody();

        if (strpos($content, '</a>') === false) {
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

        $app->setBody($content);

        return true;
    }

    private function isAdminClient(): bool
    {
        return $this->getApplication()->isClient('administrator');
    }

    /**
     * Method for get categories
     *
     * @return  array
     */
    private function getExcludedCategories(): array
    {
        $categories = $this->toIntegerList($this->params->get('excluded_categories', ''));
        $categories = array_merge($categories, $this->toIntegerList($this->params->get('excluded_category_list', [])));

        return $categories;
    }

    /**
     * Method for get categories
     *
     * @return  array
     */
    private function getExcludedMenuItems(): array
    {
        $items = $this->toIntegerList($this->params->get('excluded_menu_items', ''));
        $items = array_merge($items, $this->toIntegerList($this->params->get('excluded_menu', [])));

        return $items;
    }

    private function toIntegerList($value): array
    {
        if ($value instanceof \Joomla\Registry\Registry) {
            $value = $value->toArray();
        }

        if (is_object($value)) {
            $value = (array) $value;
        }

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        $value = array_filter($value, static function ($item): bool {
            return trim((string) $item) !== '';
        });

        return array_values(array_map('intval', $value));
    }

    /**
     * Method for check active menu item
     *
     * @return boolean
     */
    private function checkMenuItem(): bool
    {
        $menu = $this->getApplication()->getMenu();
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
        $app = $this->getApplication();
        $extension = $app->input->request->get('option');
        $view = $app->input->request->get('view');
        $id = $app->input->request->get('id');

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

            foreach ($whiteList as $url) {
                $this->excludedDomains->push($url);
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
        $exDomains = $this->normaliseSubformRows(
            $this->params->get('excluded_domains', ''),
            ['scheme', 'host', 'path']
        );

        if (!empty($exDomains)) {
            $exUris = array_map(
                static function (array $domain) {
                    $uri = new Uri();
                    $uri->setScheme($domain['scheme'] ?: '*');
                    $uri->setHost($domain['host']);
                    $uri->setPath($domain['path'] ?: '/*');

                    return $uri;
                },
                $exDomains
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
        $articleId = (int) $this->getApplication()->input->get('id');

        return $articleId > 0 && in_array($articleId, $articles, false);
    }

    /**
     * Method get current article item
     *
     * @return object|null
     */
    private function getCurrentArticle()
    {
        $app = $this->getApplication();
        $articleId = (int) $app->input->get('id');

        if (($app->input->get('option') !== 'com_content')
            || ($app->input->get('view') !== 'article') || !$articleId) {
            return null;
        }

        if (!class_exists('\Joomla\CMS\Factory')) {
            return null;
        }

        try {
            $db = \Joomla\CMS\Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'catid']))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = ' . $articleId);

            return $db->setQuery($query)->loadObject() ?: null;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * Method for create remove domains list
     *
     * @return  void
     */
    private function createRemoveList(): void
    {
        $rmDomains = $this->normaliseSubformRows(
            $this->params->get('removed_domains', ''),
            ['host']
        );

        if (!empty($rmDomains)) {
            $rmUris = array_map(
                static function (array $domain) {
                    $uri = new Uri();
                    $uri->setScheme('*');
                    $uri->setHost($domain['host']);
                    $uri->setPath('/*');

                    return $uri;
                },
                $rmDomains
            );

            $this->removedDomains->fromArray($rmUris);
        }
    }

    private function normaliseSubformRows($value, array $fields): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if ($value instanceof \Joomla\Registry\Registry) {
            $value = $value->toArray();
        }

        if (is_object($value)) {
            $value = (array) $value;
        }

        if (!is_array($value)) {
            return [];
        }

        if ($this->hasColumnArrays($value, $fields)) {
            return $this->normaliseColumnRows($value, $fields);
        }

        return $this->normaliseObjectRows($value, $fields);
    }

    private function hasColumnArrays(array $value, array $fields): bool
    {
        foreach ($fields as $field) {
            if (isset($value[$field]) && is_array($value[$field])) {
                return true;
            }
        }

        return false;
    }

    private function normaliseColumnRows(array $value, array $fields): array
    {
        $rowCount = 0;

        foreach ($fields as $field) {
            $rowCount = max($rowCount, count($value[$field] ?? []));
        }

        $rows = [];

        for ($i = 0; $i < $rowCount; $i++) {
            $row = [];

            foreach ($fields as $field) {
                $row[$field] = trim((string) ($value[$field][$i] ?? ''));
            }

            if ($this->hasRequiredHost($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function normaliseObjectRows(array $value, array $fields): array
    {
        $rows = [];

        foreach ($value as $item) {
            if (is_object($item)) {
                $item = (array) $item;
            }

            if (!is_array($item)) {
                continue;
            }

            $row = [];

            foreach ($fields as $field) {
                $row[$field] = trim((string) ($item[$field] ?? ''));
            }

            if ($this->hasRequiredHost($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function hasRequiredHost(array $row): bool
    {
        return !isset($row['host']) || $row['host'] !== '';
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
        $base  = $this->params->get('absolutize') ? rtrim(CmsUri::base(), '/') : '';
        $item  = $this->getApplication()->getMenu()->getItem($this->params->get('redirect_page'));

        if ($href && $item) {
            $query = [
                'Itemid' => (int) $item->id,
                'url' => rawurlencode($href),
            ];

            if ($item->language !== '*' && Multilanguage::isEnabled()) {
                $query['lang'] = $item->language;
            }

            return $base . Route::_('index.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986), true);
        }

        return $href;
    }
}
