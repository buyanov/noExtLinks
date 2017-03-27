<?php
/**
 * @package NoExtLinks plugin for Joomla! 3.6
 * @version $Id: noextlinks.php 599 2012-08-20 23:26:33Z buyanov $
 * @author Buyanov Danila <info@saity74.ru>
 * @copyright (C) 2012-2017 Saity74 LLC. All Rights Reserved.
 * @license GNU/GPLv2 or later; https://www.gnu.org/licenses/gpl-2.0.html
 **/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\Utilities\ArrayHelper;
use Joomla\String\StringHelper;
use Joomla\Uri\Uri;


class plgSystemNoExtLinks extends JPlugin
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
	 * @var $whitelist array
	 * @since 1.0
	 */
	protected $whitelist = [];

	/**
	 * Array of excluded html blocks
	 *
	 * @var $_blocks array
	 * @since 1.0
	 */

	protected $_blocks;

    public function onAfterRender()
	{
		$jquery_script = <<<HTML
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

		if( $this->app->isAdmin() )
		{
			return true;
		}	// Added by chris001.

		$content = $this->app->getBody();
		if (StringHelper::strpos($content, '</a>') === false)
		{
			return true;
		}

		$menu	= $this->app->getMenu();
		$active_item	= null;

		if (($menu !== null) && is_object($menu->getActive()) && property_exists($menu->getActive(), 'id')) //fixed by chris001
		{
			$active_item = $menu->getActive()->id ; //Fixed by chris001.
		}

		$items = [];
		$items_ids = $this->params->get('excluded_menu_items');

		if ($items_ids && strpos($items_ids, ',') !== false)
		{
			$items = ArrayHelper::toInteger(explode(',', $items_ids));
		}

		$items = array_merge($items, ArrayHelper::toInteger($this->params->get('excluded_menu', [])));

		if ($active_item && !empty($items) && is_array($items) && in_array($active_item, $items))
		{
			return true;
		}

		$article_id = $this->app->input->request->get('id', 0);
		$articles = explode(',', $this->params->get('excluded_articles', ''));

		if (is_array($articles) && in_array($article_id, $articles, true))
		{
			return true;
		}

		$categories = [];
		$categories_ids = $this->params->get('excluded_categories');

		if ($categories_ids && strpos($categories_ids, ',') !== false)
		{
			$categories = ArrayHelper::toInteger(explode(',', $categories_ids));
		}

		$categories = array_merge($categories, ArrayHelper::toInteger($this->params->get('excluded_categories_list', [])));

		if (!empty($categories))
		{
			if ($this->app->input->request->get('option') == 'com_content'
				&& (
                    $this->app->input->request->get('view') == 'category'
					|| $this->app->input->request->get('view') == 'blog'
				)
				&& in_array($this->app->input->request->get('id'), $categories))
			{
				return true;
			}

			$db = JFactory::getDbo();
			$query = $db->getQuery(true)
				->select($db->qn('catid'))
				->from($db->qn('#__content'))
				->where($db->qn('id') . ' = ' . $db->q((int) $article_id));

			$category_id = $db->setQuery($query)->loadResult();

			if (!empty($category_id) && in_array($category_id, $categories))
			{
				return true;
			}
		}

		$regex = '#<!-- extlinks -->(.*?)<!-- \/extlinks -->#s';
		$content = preg_replace_callback($regex, array(&$this, '_excludeBlocks'), $content);

		if ($whitelist = $this->params->get('whitelist', []))
		{
		    if (!is_array($whitelist))
                $whitelist = array_unique(explode("\n", $whitelist));

		    if (!empty($whitelist)) {
		        foreach ($whitelist as $url) {
		            if (trim($url)) {
                        $uri = new Uri(trim($url));
                        $this->whitelist += [trim($url) => $uri];
                    }
                }
            }
		}

		$ex_domains = json_decode($this->params->get('excluded_domains'), true);
		if (!empty($ex_domains) && is_array($ex_domains)) {
			$domains = array_map(array($this, '_createUri'), $ex_domains['scheme'], $ex_domains['host'], $ex_domains['path']);
		}

		$theDomain = new Uri(JUri::getInstance());
		$theDomain->setScheme('*');
        $theDomain->setPath('/*');
        $this->whitelist += [$theDomain->toString(['host', 'port']) => $theDomain];

        if (!empty($domains) && is_array($domains)) {
            $this->whitelist = array_merge($this->whitelist, ...$domains);
        }
        $content = preg_replace_callback('/<a(.+?)>(.+?)<\/a>/ius', array($this, '_replace'), $content);

		if (is_array($this->_blocks) && !empty($this->_blocks))
		{
			$this->_blocks = array_reverse($this->_blocks);
			$content = preg_replace_callback('/<!-- noExternalLinks-White-Block -->/i', array(&$this, '_includeBlocks'), $content);
		}

		if ($this->params->get('usejs'))
		{
			$content = preg_replace('/<\/body>/i', $jquery_script, $content);
		}

        $this->app->setBody($content);
		return true;
	}

    private function _replace(array $matches)
	{
        $text = $matches[0];

        if (count($matches) < 2) {
            return $text;
        }

		$args = JUtility::parseAttributes($matches[1]);

        if (!isset($args['href']) || !$args['href']) {
            return $text;
        }

        // is only fragment
        if (stripos($args['href'], '#') === 0) {
            return $text;
        }

        $uri = new Uri($args['href']);

        $host = $uri->toString(['scheme', 'host', 'port']);
        $domain = $uri->toString(['host', 'port']);

        $base = JUri::root();

        // only http(s) links
        if (!$uri->getHost() && $uri->getScheme()) {
            return $text;
        }

        if (empty($matches[2]))
        {
            return $text;
        }

        $anchor_text = $matches[2];

        $isTextAnchor = strip_tags($anchor_text) == $anchor_text;

        if (empty($host) && $uri->getPath()) {
            if (!$this->params->get('absolutize')) {
                return $text;
            }
            else {
            	$href = $args['href'];
            	unset($args['href']);
                return JHTML::link(rtrim($base, '/') . $href, $anchor_text, $args);
            }
        }

        if (!empty($host) && isset($this->whitelist[$domain])) {
            /* @var $eUri Uri */
            $eUri = $this->whitelist[$domain];
            if (($eUri->getScheme() == '*' || ($uri->getScheme() == $eUri->getScheme()))
                && ((strpos($eUri->getHost(), '*.') !== false) || ($uri->getHost() == $eUri->getHost()))
                && ((strpos($eUri->getPath(), '/') === 0) || ($uri->getPath() == $eUri->getPath()))) {

                return $text;
            }
        }

        $args['class'] = ['external-link'];

        if ($this->params->get('nofollow')) {
            $args['rel'] = 'nofollow';
        }

        if ($this->params->get('settitle') && !isset($args['title']) && ($title = trim(strip_tags($anchor_text)))) {
            $args['title'] = $title;
        }

        if ($this->params->get('replace_anchor') && $isTextAnchor) {
            $anchor_text = $args['href'];
            $args['class'][] = '--href-replaced';
        }

        if ($this->params->get('blank')) {
            $args['target'] = '_blank';
        }

        $useJS = $this->params->get('usejs');

        $props = '';
        foreach ($args as $key => $value)
        {
            $v = is_array($value) ? implode(' ', $value) : $value;
            $props .=  (!$useJS ? $key : 'data-' . $key) . '="' . $v . '" ';
        }

        $tagName = $useJS ? 'span' : 'a';

        if ($this->params->get('noindex')) {
            $text = '<noindex><'. $tagName . ' ' . $props . '>'. $anchor_text . '</'. $tagName .'></noindex>';
        }
        else {
            $text = '<' . $tagName . ' ' . $props . '>' . $anchor_text . '</' . $tagName . '>';
        }

		return $text;
	}

	protected function _excludeBlocks($matches)
	{
		$this->_blocks[] = $matches[1];
		return '<!-- noExternalLinks-White-Block -->';
	}

	/**
	 * Method for return excluded blocks into content
	 *
	 * @return  string
	 *
	 * @since 1.0
	 */
	protected function _includeBlocks()
	{
		$block = array_pop($this->_blocks);
		return '<!-- extlinks -->'.$block.'<!-- /extlinks -->';
	}

	private function _createUri($scheme, $host, $path)
    {
        $uri = new Uri();
        $uri->setScheme($scheme ?: '*');
        $uri->setHost($host);
        $uri->setPath($path ?: '/*');

        return [$uri->toString(['host']) => $uri];
    }
}
