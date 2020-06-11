<?php

namespace Buyanov\NoExtLinks\Support;

/**
 * @property string href
 * @property string target
 * @property string rel
 * @property string title
 */

class Link
{
    /**
     * @var array $class
     */
    protected $class = [];

    /**
     * @var array $args
     */
    protected $args = [];

    /**
     * @var string $anchor
     */
    protected $anchor;

    /**
     * @var string $tag
     */
    protected static $tag = 'a';

    public function __construct($href = '', $anchor = '')
    {
        if ($href) {
            $this->href = $href;
        }

        if ($anchor) {
            $this->anchor = $anchor;
        }
    }

    public static function create(): Link
    {
        return new static($href = '', $anchor = '');
    }

    public function __set($name, $value)
    {
        $this->args[$name] = $value;
    }

    public function __get($name)
    {
        return $this->args[$name];
    }

    public function __isset($name)
    {
        return isset($this->args[$name]);
    }

    public function addClass(string $class): Link
    {
        if ($class !== '') {
            $this->class[] = $class;
        }

        return $this;
    }

    public function setAnchor(string $anchor): Link
    {
        $this->anchor = $anchor;

        return $this;
    }

    public function setArgs(array $args): Link
    {
        if (isset($args['class']) && is_array($args['class'])) {
            $this->class = $args['class'];
            unset($args['class']);
        }

        $this->args = $args;

        return $this;
    }

    public function addArgs(array $args): Link
    {
        if (isset($args['class']) && is_array($args['class'])) {
            $this->class = array_merge($this->class, $args['class']);
            unset($args['class']);
            unset($this->args['class']);
        }

        $this->args = array_merge($this->args, $args);

        return $this;
    }

    protected function getClassProp(): string
    {
        return implode(' ', $this->class);
    }

    protected function getProps(bool $data = false): string
    {
        $prefix = $data ? '' : 'data-';
        $args = $this->args;

        $props = [];
        foreach ($args as $prop => $value) {
            if (null !== $value) {
                $props[] = "{$prefix}{$prop}=\"$value\"";
            } else {
                $props[] = $prefix . $prop;
            }
        }

        if (!empty($this->class)) {
            $props[] = "class=\"{$this->getClassProp()}\"";
        }

        return implode(' ', $props);
    }

    public function setTag($tag): void
    {
        self::$tag = $tag;
    }

    public function __toString()
    {
        $tag = static::$tag;

        return "<$tag {$this->getProps($tag === 'a')}>$this->anchor</$tag>";
    }
}
