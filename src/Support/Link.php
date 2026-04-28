<?php

namespace Buyanov\NoExtLinks\Support;

/**
 * @property string $href
 * @property string $target
 * @property string $rel
 * @property string $title
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

    public static function create(): Link
    {
        return new self();
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

    public function addArgs(array $args): Link
    {
        $this->args = array_merge($this->args, array_diff_key($args, $this->args));

        return $this;
    }

    protected function getClassProp(): string
    {
        return implode(' ', $this->class);
    }

    protected function splitArgsAndClasses(): array
    {
        $args = $this->args;
        $classes = $this->class;

        if (array_key_exists('class', $this->args)) {
            $classes = array_merge(explode(' ', (string) $this->args['class']), $classes);
            unset($args['class']);
        }

        return [array_filter($args), array_filter($classes)];
    }

    protected function getProps(bool $data = false): string
    {
        $prefix = $data ? '' : 'data-';
        [$args, $classes] = $this->splitArgsAndClasses();

        $props = [];

        foreach ($args as $prop => $value) {
            if (null !== $value) {
                $props[] = "{$prefix}{$prop}=\"" . $this->escape((string) $value) . '"';
            }
        }

        if (!empty($classes)) {
            $props[] = 'class="' . $this->escape(implode(' ', $classes)) . '"';
        }

        return implode(' ', $props);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function setTag($tag): void
    {
        $this->tag = $tag;
    }

    public function __toString()
    {
        $tag = $this->tag;

        return "<$tag {$this->getProps($tag === 'a')}>$this->anchor</$tag>";
    }
}
