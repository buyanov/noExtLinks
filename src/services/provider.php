<?php

defined('_JEXEC') or die;

use Buyanov\NoExtLinks\PlgSystemNoExtLinks;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

require_once dirname(__DIR__) . '/noextlinks.php';

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container): PlgSystemNoExtLinks {
                $plugin = new PlgSystemNoExtLinks(
                    (array) PluginHelper::getPlugin('system', 'noextlinks')
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
