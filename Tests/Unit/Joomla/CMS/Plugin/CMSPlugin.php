<?php

namespace Joomla\CMS\Plugin;

class CMSPlugin
{
    protected $params;

    private $application;

    public function __construct(array $config = [])
    {
        $this->params = $config['params'] ?? null;
    }

    public function setApplication($application): void
    {
        $this->application = $application;
    }

    protected function getApplication()
    {
        return $this->application;
    }
}
