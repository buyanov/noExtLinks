<?php
/*------------------------------------------------------------------------
# plg_noextlinks   Fixes by Chris001 (github) of chris@espacenetworks.com
# ------------------------------------------------------------------------
# author &nbsp; &nbsp;Buyanov Danila - Saity74 Ltd.
# copyright Copyright (C) 2012 saity74.ru. All Rights Reserved.
# @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
# Websites: http://www.saity74.ru
# Technical Support: &nbsp; http://saity74.ru/no-external-links-joomla.html
# Admin E-mail: admin@saity74.ru
-------------------------------------------------------------------------*/
// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' ); 

jimport('joomla.plugin.plugin');

class plgSystemNoExtLinks extends JPlugin
{
	protected $_addblank;
	protected $_addNoindex;
	protected $_addNofollow;
	protected $_addTitle;
	protected $_whitelist;
	protected $_blocks;

    public function onAfterRender()
	{
		$app =& JFactory::getApplication(); if( $app->isAdmin() ) return true;	// Added by chris001.
		$content = JResponse::getBody();
		if (JString::strpos($content, '</a>') === false) {
			return true;
		}
		
		$app		= JFactory::getApplication();
		$menu		= $app->getMenu();
		$active_item	= null;  if (isset($menu->getActive()->id)) $active_item = $menu->getActive()->id ; //Fixed by chris001.
		
		$items = explode(',', $this->params->get('excluded_menu_items', ''));
		
		if (is_array($items) && in_array($active_item, $items))
			return true;
			
		$article_id = JRequest::getVar('id', 0);
		$articles = explode(',', $this->params->get('excluded_articles', ''));

		if (is_array($articles) && in_array($article_id, $articles))
			return true;
		
		$categories = $this->params->get('excluded_categories', '');
		$categories = ($categories !== '') ? explode(',', $categories) : '';
		JArrayHelper::toInteger($categories, NULL);
		if (count($categories) > 0)
		{
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('catid');
			$query->from('#__content');
			$query->where('id = '.$article_id);
			$category_id = $db->setQuery($query)->loadResult();
			if (in_array($category_id, $categories))
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
		
		$whitelist = $this->params->get('whitelist', '');
		
		if ($whitelist)
			$this->_whitelist = explode("\n",$whitelist);
		
		$uri = JFactory::getURI();
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
		
		JResponse::setBody($content);
		return true;
	}
	
	protected function _replace(&$matches)
	{
		jimport('joomla.utilities.utility');

		$args = JUtility::parseAttributes($matches[1]);
		
		$parse = parse_url($args['href']);
		if (isset($parse['host']) && (!$parse['host']))  //Fixed by chris001.
		{
			$uri = JFactory::getURI();
			$parse['host'] = $uri->getHost();
		}
		
		if (isset($parse['host']) && !$this->_in_wl($parse['host']))  //Fixed by chris001.
		{
			$params = '';
			
			if ($this->_addNofollow)
				$args['rel'] = 'nofollow';
			else
				unset($args['nofollow']);
			
			if ($this->_addTitle && (isset($args['title'])) && !$args['title'])  //Fixed by chris001.
			{
				$title = trim(strip_tags($matches[2]));
				if ($title)
					$args['title'] = $title;
			}
			
			
			if ($this->_addblank)
				$args['target'] = '_blank';
			else
				unset($args['target']);
			
			
				
			foreach ($args as $key => $value)
			{
				$params .= $key.'="'.$value.'" ';
			}
			if ($this->_addNoindex)
				$text = '<noindex><a '.$params.'>'.$matches[2].'</a></noindex>';
			else
				$text = '<a '.$params.'>'.$matches[2].'</a>';

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
	
	protected function _includeBlocks($matches)
	{
		$block = array_pop($this->_blocks);
		return '<!-- extlinks -->'.$block.'<!-- /extlinks -->';
	}
	
	protected function _in_wl($host)
	{
		foreach ($this->_whitelist as $wh)
		{
			$find = trim(str_replace('*','',$wh));
			if (stripos($host, $find) !== false)
			{
				return true;
			}
		}
		return false;
	}
}