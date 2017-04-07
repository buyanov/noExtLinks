<?php
/**
 * @package Joomla.plugin
 * @subpackage  System.noextlinks
 *
 * @author Buyanov Danila <info@saity74.ru>
 * @copyright (C) 2012-2017 Saity74 LLC. All Rights Reserved.
 * @license GNU/GPLv2 or later; https://www.gnu.org/licenses/gpl-2.0.html
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
	protected $blocks;

	/**
	 * Method on After render
	 *
	 * @return boolean
	 * @since 1.0
	 */
	public function onAfterRender()
	{
		$jqueryScript = <<<HTML
<script type="text/javascript">
    jQuery(document).ready(function() {
        jQuery("span.external-link").each(function(i, el) {
            var data = jQuery(el).data();
            jQuery(el).wrap(jQuery("<a>").attr({
                "href" : data.href, 
                "title" : data.title, 
                "target" : data.target
            }))
        })
    })
</script></body>
HTML;

		// Added by chris001.
		if ($this->app->isAdmin())
		{
			return true;
		}

		$content = $this->app->getBody();

		if (StringHelper::strpos($content, '</a>') === false)
		{
			return true;
		}

		$menu	= $this->app->getMenu();
		$activeItem	= null;

		// Fixed by chris001
		if (($menu !== null) && is_object($menu->getActive()) && property_exists($menu->getActive(), 'id'))
		{
			$activeItem = $menu->getActive()->id;
		}

		$items = array();
		$itemsIds = $this->params->get('excluded_menu_items');

		if ($itemsIds && strpos($itemsIds, ',') !== false)
		{
			$items = ArrayHelper::toInteger(explode(',', $itemsIds));
		}

		$items = array_merge($items, ArrayHelper::toInteger($this->params->get('excluded_menu', array())));

		if ($activeItem && !empty($items) && is_array($items) && in_array($activeItem, $items))
		{
			return true;
		}

		$articleId = $this->app->input->request->get('id', 0);
		$articles = explode(',', $this->params->get('excluded_articles', ''));

		if (is_array($articles) && in_array($articleId, $articles, true))
		{
			return true;
		}

		$categories = array();
		$categoriesIds = $this->params->get('excluded_categories');

		if ($categoriesIds && strpos($categoriesIds, ',') !== false)
		{
			$categories = ArrayHelper::toInteger(explode(',', $categoriesIds));
		}

		$categories = array_merge($categories, ArrayHelper::toInteger($this->params->get('excluded_categories_list', array())));

		if (!empty($categories))
		{
			if ($this->app->input->request->get('option') == 'com_content'
				&& (($this->app->input->request->get('view') == 'category') || ($this->app->input->request->get('view') == 'blog'))
				&& in_array($this->app->input->request->get('id'), $categories))
			{
				return true;
			}

			$db = JFactory::getDbo();
			$query = $db->getQuery(true)
				->select($db->qn('catid'))
				->from($db->qn('#__content'))
				->where($db->qn('id') . ' = ' . $db->q((int) $articleId));

			$categoryId = $db->setQuery($query)->loadResult();

			if (!empty($categoryId) && in_array($categoryId, $categories))
			{
				return true;
			}
		}

		$regex = '#<!-- extlinks -->(.*?)<!-- \/extlinks -->#s';
		$content = preg_replace_callback($regex, array(&$this, 'excludeBlocks'), $content);

		if ($whiteList = $this->params->get('whitelist', array()))
		{
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
		}

		$exDomains = json_decode($this->params->get('excluded_domains'), true);

		if (!empty($exDomains) && is_array($exDomains))
		{
			$domains = array_map(array($this, 'createUri'), $exDomains['scheme'], $exDomains['host'], $exDomains['path']);
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

		$content = preg_replace_callback('/<a (.+?)>(.+?)<\/a>/ius', array($this, 'replace'), $content);

		if (is_array($this->blocks) && !empty($this->blocks))
		{
			$this->blocks = array_reverse($this->blocks);
			$content      = preg_replace_callback('/<!-- noExternalLinks-White-Block -->/i', array(&$this, 'includeBlocks'), $content);
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
	 * @return string
	 * @since 1.0
	 */
	private function replace(array $matches)
	{
		$text = $matches[0];

		if (count($matches) < 2)
		{
			return $text;
		}

		$args = JUtility::parseAttributes($matches[1]);

		if (!isset($args['href']) || !$args['href'])
		{
			return $text;
		}

		// Is only fragment
		if (stripos($args['href'], '#') === 0)
		{
			return $text;
		}

		$uri = new Uri($args['href']);
		$host = $uri->toString(array('scheme', 'host', 'port'));
		$domain = $uri->toString(array('host', 'port'));
		$base = JUri::root();

		// Only http(s) links
		if (!$uri->getHost() && $uri->getScheme())
		{
			return $text;
		}

		if (empty($matches[2]))
		{
			return $text;
		}

		$anchorText = $matches[2];

		$isTextAnchor = strip_tags($anchorText) == $anchorText;

		if (empty($host) && $uri->getPath())
		{
			if (!$this->params->get('absolutize'))
			{
				return $text;
			}
			else
			{
				$href = $args['href'];
				unset($args['href']);

				return JHTML::link(rtrim($base, '/') . $href, $anchorText, $args);
			}
		}

		if (!empty($host) && isset($this->whiteList[$domain]))
		{
			$eUri = $this->whiteList[$domain];

			if (($eUri->getScheme() == '*' || ($uri->getScheme() == $eUri->getScheme()))
				&& ((strpos($eUri->getHost(), '*.') !== false) || ($uri->getHost() == $eUri->getHost()))
				&& ((strpos($eUri->getPath(), '/') === 0) || ($uri->getPath() == $eUri->getPath())))
			{
				return $text;
			}
		}

		$args['class'] = array('external-link');

		if ($this->params->get('nofollow'))
		{
			$args['rel'] = 'nofollow';
		}

		if ($this->params->get('settitle') && !isset($args['title']) && ($title = trim(strip_tags($anchorText))))
		{
			$args['title'] = $title;
		}

		if ($this->params->get('replace_anchor') && $isTextAnchor)
		{
			$anchorText = $args['href'];
			$args['class'][] = '--href-replaced';
		}

		if ($this->params->get('blank'))
		{
			$args['target'] = '_blank';
		}

		$useJS = $this->params->get('usejs');

		$props = '';

		foreach ($args as $key => $value)
		{
			$v = is_array($value) ? implode(' ', $value) : $value;
			$props .= (!$useJS ? $key : 'data-' . $key) . '="' . $v . '" ';
		}

		$tagName = $useJS ? 'span' : 'a';

		if ($this->params->get('noindex'))
		{
			$text = '<!--noindex--><' . $tagName . ' ' . $props . '>' . $anchorText . '</' . $tagName . '><!--/noindex-->';
		}
		else
		{
			$text = '<' . $tagName . ' ' . $props . '>' . $anchorText . '</' . $tagName . '>';
		}

		return $text;
	}

	/**
	 * Method for replace white blocks
	 *
	 * @param   array  $matches  Array of blocks
	 *
	 * @return string
	 * @since 1.0
	 */
	private function excludeBlocks($matches)
	{
		$this->blocks[] = $matches[1];

		return '<!-- noExternalLinks-White-Block -->';
	}

	/**
	 * Method for return excluded blocks into content
	 *
	 * @return  string
	 *
	 * @since 1.0
	 */
	private function includeBlocks()
	{
		$block = array_pop($this->blocks);

		return '<!-- extlinks -->' . $block . '<!-- /extlinks -->';
	}

	/**
	 * Method for create Uri string from parts
	 *
	 * @param   string  $scheme  Uri scheme
	 * @param   string  $host    Uri host
	 * @param   string  $path    Uri path
	 *
	 * @return array
	 * @since 1.0
	 */
	private function createUri($scheme, $host, $path)
	{
		$uri = new Uri;
		$uri->setScheme($scheme ?: '*');
		$uri->setHost($host);
		$uri->setPath($path ?: '/*');

		return array($uri->toString(array('host')) => $uri);
	}
}
