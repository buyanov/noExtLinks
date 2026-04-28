<?php

namespace Joomla\Event;

interface SubscriberInterface
{
    public static function getSubscribedEvents(): array;
}
