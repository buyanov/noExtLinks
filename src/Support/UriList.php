<?php

declare(strict_types=1);

namespace Buyanov\NoExtLinks\Support;

use Countable;
use Joomla\Uri\Uri;

class UriList implements Countable
{
    protected $stack = [];

    public function count(): int
    {
        return count($this->stack);
    }

    /**
     * Method for push new uri to stack.
     *
     * @param string|Uri $uri
     */
    public function push($uri): void
    {
        $this->stack[] = $uri instanceof Uri ? $uri : $this->createUri($uri);
    }

    public function fromArray(array $array): void
    {
        $this->stack = array_map([$this, 'map'], $array);
    }

    /**
     * Returns the content of this class as array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->stack;
    }

    /**
     * Method for merging two lists.
     *
     * @param UriList $list
     */
    public function merge(UriList $list): void
    {
        $this->stack = array_merge($this->stack, $list->toArray());
    }

    public function createUri($maskedUri): Uri
    {
        ['scheme' => $scheme, 'host' => $host, 'path' => $path] = $this->parseUri($maskedUri);

        $uri = new Uri();
        $uri->setScheme($scheme ?: '*');
        $uri->setHost($host);
        $uri->setPath($path ?: '/*');

        return $uri;
    }

    /**
     * @param string $uri
     *
     * @return array
     */
    public function parseUri(string $uri): array
    {
        $rx = '~^(?P<scheme>[^:]*):(?:\/\/)?(?P<host>[^\/]*)(?P<path>.*)$~iu';
        preg_match($rx, $uri, $matches);

        return [
            'scheme' => $matches['scheme'],
            'host'   => $matches['host'],
            'path'   => $matches['path'],
        ];
    }

    /**
     * @param string $uri
     *
     * @return Uri
     */
    public function map(string $uri): Uri
    {
        return $this->isMasked($uri) ? $this->createUri($uri) : new Uri($uri);
    }

    /**
     * @param string      $scheme
     * @param string      $host
     * @param string      $port
     * @param string|null $path
     * @param string|null $query
     * @param string|null $fragment
     */
    public function pushByParts(
        string $scheme,
        string $host,
        string $port,
        ?string $path = null,
        ?string $query = null,
        ?string $fragment = null
    ): void {
        $uri = new Uri();
        $uri->setScheme($scheme ?? '');
        $uri->setHost($host ?? '');
        $uri->setPort($port ?? '');
        $uri->setPath($path ?? '');
        $uri->setQuery($query ?? '');
        $uri->setFragment($fragment ?? '');

        $this->push($uri);
    }

    /**
     * @param $uri
     *
     * @return bool
     */
    public function exists($uri): bool
    {
        return !empty(array_filter($this->stack, function ($current) use ($uri) {
            return $this->compare($uri, $current);
        }));
    }

    /**
     * @param $uri
     *
     * @return bool
     */
    public function isMasked($uri): bool
    {
        return strpos((string) $uri, '*') !== false;
    }

    public function compare($needle, $fromList): bool
    {
        return $this->isMasked($fromList) ?
            $this->compareByParts($needle, $fromList) :
            $needle === (string) $fromList;
    }

    public function compareByParts($needle, Uri $fromList): bool
    {
        $regex = [];

        if ($fromList->getScheme() === '*') {
            $regex[] = 'http[s]?\://';
        } else {
            $regex[] = $fromList->getScheme() . '\://';
        }

        if ($host = $fromList->toString(['host', 'port'])) {
            $regex[] = str_replace('*', '[\w\-]+', $host);
        }

        if ($path = $fromList->getPath()) {
            $regex[] = str_replace('/*', '(\/[\w\-\~\:\.\/]*|)', $path);
        }

        $rx     = '~^' . implode('', $regex) . '$~iU';
        $needle = $needle instanceof Uri ? $needle : new Uri($needle);

        if (preg_match($rx, $needle->toString(['scheme', 'host', 'port', 'path']))) {
            return true;
        }

        return false;
    }
}
