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
     * Method for only tests
     * @return mixed
     */
    public function getApp()
    {
        return $this->app;
    }
}

class ContentModelArticle
{
    public function getItem()
    {
        return (object) ['id' => 1];
    }
}