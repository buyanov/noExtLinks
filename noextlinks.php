<?php

// No direct access
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

class plgContentNoExtLinks extends JPlugin
{
	protected $_addblank;
	protected $_addNoindex;
	protected $_addNofollow;
	protected $_addTitle;
	protected $_whitelist;

    public function onContentPrepare($context, &$article, &$params, $page = 0)
	{
		if (JString::strpos($article->text, '</a>') === false) {
			return true;
		}
		
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

		$article->text = preg_replace_callback($regex, array(&$this, '_replace'), $article->text);
		
		return true;
	}
	
	protected function _replace(&$matches)
	{
		jimport('joomla.utilities.utility');

		$args = JUtility::parseAttributes($matches[1]);
		
		
		$parse = parse_url($args['href']);

		if (!$this->_in_wl($parse['host']))
		{
			$params = '';
			
			if ($this->_addNofollow)
				$args['rel'] = 'nofollow';
			else
				unset($args['nofollow']);
			
			if ($this->_addTitle && !$args['title'])
				$args['title'] = $matches[2];
			
			
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