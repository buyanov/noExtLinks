<?php
/**
 * @package     Joomla.plugin
 * @subpackage  System.noextlinks
 *
 * @author      Buyanov Danila <info@saity74.ru>
 * @copyright   (C) 2012-2017 Saity74 LLC. All Rights Reserved.
 * @license     GNU/GPLv2 or later; https://www.gnu.org/licenses/gpl-2.0.html
 **/

defined('_JEXEC') or die;

use Joomla\Utilities\ArrayHelper;
use Joomla\String\StringHelper;
use Joomla\Uri\Uri;

/**
 * Class PlgSystemNoExtLinks
 *
 * @since 3.2
 */
class PlgSystemNoExtLinks extends JPlugin
{
	/**
	 * Object of Joomla! application class
	 *
	 * @var $app JApplicationSite
	 * @since 1.0
	 */
	public $app;

	/**
	 * Array of excluded domains
	 *
	 * @var $whiteList array
	 * @since 1.0
	 */
	protected $whiteList = array();

	/**
	 * Array of excluded html blocks
	 *
	 * @var $blocks array
	 * @since 1.0
	 */
	protected static $blocks;

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

		$this->createWhiteList();
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

		if ($this->app->isAdmin())
		{
			return true;
		}

		$content = $this->app->getBody();

		if (StringHelper::strpos($content, '</a>') === false)
		{
			return true;
		}

		if ($this->checkArticle() || $this->checkMenuItem() || $this->checkCategory())
		{
			return true;
		}

		$content = preg_replace_callback('#<!-- extlinks -->(.*?)<!-- \/extlinks -->#s', 'static::excludeBlocks', $content);

		$regex = '/<a(?:\s*?)(?P<args>(?=(?:[^>=]|=")*?\shref="(?=[\w]|[\/\.#])(?P<href>[^"]*)")[^<>]*)>(?P<anchor>.*?)<\/a>/ius';
		$content = preg_replace_callback($regex, array($this, 'replace'), $content);

		if (is_array(static::$blocks) && !empty(static::$blocks))
		{
			static::$blocks = array_reverse(static::$blocks);
			$content = preg_replace_callback('/<!-- noExternalLinks-White-Block -->/i', 'static::includeBlocks', $content);
		}

		if ($this->params->get('usejs'))
		{
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

		if (stripos($matches['href'], '#') === 0)
		{
			return $text;
		}

		$anchor = $matches['anchor'];
		$base = JUri::root();
		$href = $matches['href'];
		$args = JUtility::parseAttributes($matches['args']);
		$uri = new Uri($href);

		if (empty($anchor) || (!$uri->getHost() && $uri->getScheme()))
		{
			return $text;
		}

		if ($this->isRelativeUri($uri))
		{
			if ($this->params->get('absolutize'))
			{
				unset($args['href']);
				$text = JHTML::link(rtrim($base, '/') . $href, $anchor, $args);
			}

			return $text;
		}

		if ($this->isExcludedUri($uri))
		{
			return $text;
		}

		$text = $this->link($anchor, $args);

		if ($this->params->get('noindex'))
		{
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
	 * @param   string  $scheme  Uri scheme
	 * @param   string  $host    Uri host
	 * @param   string  $path    Uri path
	 *
	 * @return  array
	 * @since 1.0
	 */
	protected static function createUri($scheme, $host, $path)
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
		$categories = array_merge($categories, ArrayHelper::toInteger($this->params->get('excluded_category_list', array())));

		return $categories;
	}

	/**
	 * Check the buffer.
	 *
	 * @param   string  $match  Buffer to be checked.
	 *
	 * @return  void
	 * @since   1.7.5
	 */
	private function checkMatch($match)
	{
		if ($match === null)
		{
			switch (preg_last_error())
			{
				case PREG_BACKTRACK_LIMIT_ERROR:
					$message = "PHP regular expression limit reached (pcre.backtrack_limit)";
					break;
				case PREG_RECURSION_LIMIT_ERROR:
					$message = "PHP regular expression limit reached (pcre.recursion_limit)";
					break;
				case PREG_BAD_UTF8_ERROR:
					$message = "Bad UTF8 passed to PCRE function";
					break;
				default:
					$message = "Unknown PCRE error calling PCRE function";
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

		if (!empty($domain) && isset($this->whiteList[$domain]))
		{
			$eUri = $this->whiteList[$domain];

			if (($eUri->getScheme() == '*' || ($uri->getScheme() == $eUri->getScheme()))
				&& ((strpos($eUri->getHost(), '*.') !== false) || ($uri->getHost() == $eUri->getHost()))
				&& ((strpos($eUri->getPath(), '/') === 0) || ($uri->getPath() == $eUri->getPath())))
			{
				$isExcluded = true;
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

		if ($menu = $this->app->getMenu())
		{
			if ($activeItem	= $menu->getActive())
			{
				$items = $this->getExcludedMenuItems();

				$result = !empty($items) && is_array($items) && in_array($activeItem->id, $items);
			}
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

		if (!empty($categories) && $extension == 'com_content')
		{
			if (($view == 'blog' || $view == 'category') && in_array($id, $categories))
			{
				return true;
			}

			$currentArticle = $this->getCurrentArticle();

			$result = $currentArticle && in_array($currentArticle->catid, $categories);
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
		$whiteList = $this->params->get('whitelist', array());

		if (!is_array($whiteList))
		{
			$whiteList = array_unique(explode("\n", $whiteList));
		}

		if (!empty($whiteList))
		{
			foreach ($whiteList as $url)
			{
				if (trim($url))
				{
					$uri = new Uri(trim($url));
					$this->whiteList += array(trim($url) => $uri);
				}
			}
		}

		$exDomains = json_decode($this->params->get('excluded_domains'), true);

		if (!empty($exDomains) && is_array($exDomains))
		{
			$domains = array_map("static::createUri", $exDomains['scheme'], $exDomains['host'], $exDomains['path']);
		}

		$theDomain = new Uri(JUri::getInstance());
		$theDomain->setScheme('*');
		$theDomain->setPath('/*');
		$this->whiteList += array($theDomain->toString(array('host', 'port')) => $theDomain);

		if (!empty($domains) && is_array($domains))
		{
			// For php 5.6 use unpack operator
			$this->whiteList = array_merge($this->whiteList, call_user_func_array('array_merge', $domains));
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

		return (is_object($article) && is_array($articles) && in_array($article->id, $articles));
	}

	/**
	 * Method get current article item
	 *
	 * @return object|null
	 * @since  1.7.5
	 */
	private function getCurrentArticle()
	{

		if (!JLoader::import('models.article', JPATH_COMPONENT_SITE))
		{
			return null;
		}

		$articleModel = new ContentModelArticle();
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
		$args['class'] = array('external-link');
		$args['rel'] = $this->params->get('nofollow');
		$args['target'] = $this->params->get('blank');
		$anchorText = trim(strip_tags($anchor));

		if ($this->params->get('settitle') && !isset($args['title']) && $anchorText)
		{
			$args['title'] = $anchorText;
			$args['class'][] = '--set-title';
		}

		if ($this->params->get('replace_anchor') && $anchorText == $anchor)
		{
			$anchor = $args['href'];
			$args['class'][] = '--href-replaced';
		}

		if ($useJS = $this->params->get('usejs'))
		{
			$args['class'][] = 'js-modify';
		}

		$args = array_filter($args);

		$props = '';

		foreach ($args as $key => $value)
		{
			$v = is_array($value) ? implode(' ', $value) : $value;
			if ($useJS && $key !== 'class')
			{
				$key = 'data-' . $key;
			}
			$props .= $key . '="' . $v . '" ';
		}

		$tagName = $useJS ? 'span' : 'a';

		return '<' . $tagName . ' ' . $props . '>' . $anchor . '</' . $tagName . '>';
	}
}
