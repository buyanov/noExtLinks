<?php

namespace Joomla\CMS\Application;

class CMSApplication
{
    /** @var object */
    public $input;

    public function getDocument(): object
    {
    }

    public function getBody(): string
    {
    }

    public function setBody(string $content): void
    {
    }

    public function getMenu(): ?object
    {
    }

    public function isClient(string $identifier): bool
    {
    }

    public function isAdmin(): bool
    {
    }
}

namespace Joomla\CMS\Event\Application;

class BeforeRenderEvent
{
}

class AfterRenderEvent
{
}

namespace Joomla\CMS\Extension;

interface PluginInterface
{
}

namespace Joomla\CMS;

use Joomla\CMS\Application\CMSApplication;

class Factory
{
    public static function getApplication(): CMSApplication
    {
    }

    public static function getDbo(): object
    {
    }
}

namespace Joomla\CMS\Language;

class Multilanguage
{
    public static function isEnabled(): bool
    {
    }
}

namespace Joomla\CMS\Plugin;

use Joomla\CMS\Application\CMSApplication;

class CMSPlugin
{
    /** @var \Joomla\Registry\Registry */
    public $params;

    public function __construct(array $config = [])
    {
    }

    public function setApplication(CMSApplication $application): void
    {
    }

    protected function getApplication(): CMSApplication
    {
    }
}

class PluginHelper
{
    public static function getPlugin(string $type, string $plugin): object
    {
    }
}

namespace Joomla\CMS\Router;

class Route
{
    public static function _(string $url, bool $xhtml = true, ?int $tls = null): string
    {
    }
}

namespace Joomla\CMS\Uri;

class Uri
{
    public static function base(bool $pathonly = false): string
    {
    }
}

namespace Joomla\DI;

class Container
{
    /**
     * @param mixed $key
     * @param mixed $value
     */
    public function set($key, $value): void
    {
    }
}

interface ServiceProviderInterface
{
    public function register(Container $container): void;
}

namespace Joomla\Event;

interface SubscriberInterface
{
    public static function getSubscribedEvents(): array;
}
