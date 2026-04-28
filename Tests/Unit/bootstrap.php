<?php

use Tests\Unit\Joomla\Core\Mock\TestMockApplication;

define('_JEXEC', 1);

define('JPATH_PLATFORM', 1);
define('JPATH_COMPONENT_SITE', 1);

define('TESTS_ENV', 1);

// Fix magic quotes.
ini_set('magic_quotes_runtime', 0);

// Maximise error reporting.
ini_set('zend.ze1_compatibility_mode', '0');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../vendor/autoload.php';

abstract class JLoader
{
    public static function import($path): bool
    {
        return true;
    }
}

abstract class JPlugin
{
    protected $params;

    public function __construct($subject, $config)
    {
        $this->params = $config['params'];
    }

    /**
     * Method for tests only
     * @return mixed
     */
    public function getApp()
    {
        return $this->app;
    }
}

class JApplication
{
    public $input;

    public function get()
    {
    }

    public function getCfg()
    {
    }

    public function getIdentity()
    {
    }

    public function getRouter()
    {
    }

    public function getTemplate()
    {
    }

    public function getDocument()
    {
    }

    public function getMenu()
    {
    }

    public function getLanguage()
    {
    }

    public function isClient()
    {
    }

    public function isAdmin()
    {
    }

    public function appendBody()
    {
    }

    public function getBody()
    {
    }

    public function prependBody()
    {
    }

    public function setBody()
    {
    }
}

class JDocument
{
    public function parse()
    {
    }

    public function setMetaData()
    {
    }

    public function render()
    {
    }
}

class JEventDispatcher
{
    public function register()
    {
    }

    public function trigger()
    {
    }

    public function test()
    {
    }
}

class JLanguage
{
    public function _()
    {
    }

    public function getInstance()
    {
    }

    public function getTag()
    {
    }

    public function isRTL()
    {
    }

    public function test()
    {
    }
}

class JMenu
{
    public function getItem()
    {
    }

    public function setDefault()
    {
    }

    public function getDefault()
    {
    }

    public function setActive()
    {
    }

    public function getActive()
    {
    }

    public function getItems()
    {
    }

    public function getParams()
    {
    }

    public function getMenu()
    {
    }

    public function authorise()
    {
    }

    public function load()
    {
    }
}

class JSession
{
    public function clear()
    {
    }

    public function close()
    {
    }

    public function destroy()
    {
    }

    public function fork()
    {
    }

    public function get()
    {
    }

    public function getExpire()
    {
    }

    public function getFormToken()
    {
    }

    public function getId()
    {
    }

    public function getInstance()
    {
    }

    public function getName()
    {
    }

    public function getState()
    {
    }

    public function getStores()
    {
    }

    public function getToken()
    {
    }

    public function has()
    {
    }

    public function hasToken()
    {
    }

    public function getPrefix()
    {
    }

    public function isNew()
    {
    }

    public function restart()
    {
    }

    public function set()
    {
    }
}

class JUser
{
}

class ContentModelArticle
{
    public function getItem()
    {
        return (object) ['id' => 1];
    }
}
