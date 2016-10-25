<?php
/*------------------------------------------------------------------------
# plg_noextlinks   Fixes by Chris001 (github) of chris@espacenetworks.com
# ------------------------------------------------------------------------
# author &nbsp; &nbsp;Buyanov Danila - Saity74 Ltd.
# copyright Copyright (C) 2012-2016 saity74.ru. All Rights Reserved.
# @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
# Websites: https://www.saity74.ru
# Technical Support: &nbsp; https://saity74.ru/no-external-links-joomla.html
# Admin E-mail: admin@saity74.ru
-------------------------------------------------------------------------*/
// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' ); 

use Joomla\Utilities\ArrayHelper;
use Joomla\String\StringHelper;

jimport('joomla.plugin.plugin');

class plgSystemNoExtLinks extends JPlugin
{
	protected $_addblank;
	protected $_addNoindex;
	protected $_addNofollow;
	protected $_addTitle;
	protected $_whitelist;
	protected $_blocks;
	protected $_usejs;

    public function onAfterRender()
	{
		$jquery_script = "<script type=\"text/javascript\">jQuery(document).ready(function(){jQuery('span.external-link').each(function(i, el){var data = jQuery(el).data(); jQuery(el).wrap(jQuery('<a>').attr({'href' : data.href, 'title' : data.title, 'target' : data.target}))})})</script></body>";
		
		$app = JFactory::getApplication();

		if( $app->isAdmin() )
		{
			return true;
		}	// Added by chris001.

		$content = $app->getBody();
		if (StringHelper::strpos($content, '</a>') === false)
		{
			return true;
		}

		$app	= JFactory::getApplication();
		$menu	= $app->getMenu();
		$active_item	= null;

		if ($menu !== null && property_exists($menu->getActive(), 'id'))
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

		$article_id = $app->input->request->get('id', 0);
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
			if ($app->input->request->get('option') == 'com_content'
				&& (
					$app->input->request->get('view') == 'category'
					|| $app->input->request->get('view') == 'blog'
				)
				&& in_array($app->input->request->get('id'), $categories))
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

		$this->_addblank = $this->params->get('blank', '_blank') == '_blank';
		$this->_addNoindex = $this->params->get('noindex', '1') == '1';
		$this->_addNofollow = $this->params->get('nofollow', '1') == '1';
		$this->_addTitle = $this->params->get('settitle', '1') == '1';
		$this->_usejs = $this->params->get('usejs', 0);
		$whitelist = $this->params->get('whitelist', '');
		
		if ($whitelist)
		{
			$this->_whitelist = explode("\n",$whitelist);
		}

		$uri = JUri::getInstance();
		$host = $uri->getHost();

		$this->_whitelist[] = $host;

		$regex = "#<a(.*?)>(.*?)</a>#s";

		$content = preg_replace_callback($regex, array(&$this, '_replace'), $content);
		
		if (is_array($this->_blocks) && !empty($this->_blocks))
		{
			$this->_blocks = array_reverse($this->_blocks);
			$regex = '#<!-- noExternalLinks-White-Block -->#s';
			$content = preg_replace_callback($regex, array(&$this, '_includeBlocks'), $content);
		}

		if ($this->_usejs)
		{
			$content = preg_replace('#<\/body>#s', $jquery_script, $content);
		}

		$app->setBody($content);
		return true;
	}
	
	protected function _replace(&$matches)
	{
		jimport('joomla.utilities.utility');

		$args = JUtility::parseAttributes($matches[1]);
		
		$parse = isset($args['href']) ? parse_url($args['href']) : null ;	//fixed by chris001 6-nov-2012.
		if (isset($parse['host']) && (!$parse['host']))  //Fixed by chris001.
		{
			$uri = JUri::getInstance();
			$parse['host'] = $uri->getHost();
		}

		if (isset($parse['host']) && !$this->_in_wl($parse['host']))  //Fixed by chris001.
		{
			$params = '';

			if ($this->_addNofollow)
			{
				$args['rel'] = 'nofollow';
			}
			else
			{
				unset($args['nofollow']);
			}

			if ($this->_addTitle && (isset($args['title'])) && !$args['title'])  //Fixed by chris001.
			{
				$title = trim(strip_tags($matches[2]));
				if ($title)
				{
					$args['title'] = $title;
				}
			}

			if ($this->_addblank)
			{
				$args['target'] = '_blank';
			}
			else
			{
				unset($args['target']);
			}

			$params = '';
			foreach ($args as $key => $value)
			{
				$params .=  ($this->_usejs ? 'data-' : '') . $key . '="' . $value.'" ';
			}

			if ($this->_usejs)
			{
				$params .= 'class="external-link"';
				$text = '<span '.$params.'>' . $matches[2] . '</span>';
			}
			else
			{
				if ($this->_addNoindex)
				{
					$text = '<noindex><a ' . $params . '>'.$matches[2] . '</a></noindex>';
				}
				else
				{
					$text = '<a ' . $params . '>' . $matches[2] . '</a>';
				}
			}
		}
		else
			$text = $matches[0];

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
	 * @param   array  $matches
	 *
	 * @return  string
	 *
	 * @since 1.0
	 */
	protected function _includeBlocks($matches)
	{
		$block = array_pop($this->_blocks);
		return '<!-- extlinks -->'.$block.'<!-- /extlinks -->';
	}

	/**
	 * Method for search URL in white list
	 *
	 * @param   string  $host  URL
	 *
	 * @return  bool
	 *
	 * @since 1.0
	 */
	protected function _in_wl($host)
	{
		foreach ($this->_whitelist as $wh)
		{
			$find = trim(str_replace('*', '', $wh));
			if (stripos($host, $find) !== false)
			{
				return true;
			}
		}
		return false;
	}
}
