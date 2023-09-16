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
    protected $tag = 'a';

    public function __construct($href = '', $anchor = '')
    {
        if ($href) {
            $this->href = $href;
        }

        if ($anchor) {
            $this->anchor = $anchor;
        }
    }

    public function __set($name, $value)
    {
        if ($name === 'class') {
            return;
        }

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

    public function __toString()
    {
        $tag = $this->tag;

        return "<{$tag} {$this->getProps($tag === 'a')}>{$this->anchor}</{$tag}>";
    }

    public static function create(): Link
    {
        return new static();
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
        $this->args = $args;

        return $this;
    }

    public function setTag($tag): void
    {
        $this->tag = $tag;
    }

    protected function getClassProp(): string
    {
        return implode(' ', $this->class);
    }

    protected function filterArgs(): void
    {
        if (array_key_exists('class', $this->args)) {
            $this->class = array_merge(explode(' ', $this->args['class']), $this->class);
            unset($this->args['class']);
        }

        $this->args = array_filter($this->args);
    }

    protected function getProps(bool $data = false): string
    {
        $prefix = $data ? '' : 'data-';
        $this->filterArgs();

        $props = [];

        foreach ($this->args as $prop => $value) {
            if ($value !== null) {
                $props[] = "{$prefix}{$prop}=\"{$value}\"";
            }
        }

        if (!empty($this->class)) {
            $props[] = "class=\"{$this->getClassProp()}\"";
        }

        return implode(' ', $props);
    }
}
