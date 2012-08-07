<?php
namespace shozu\Twig;
/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Loads template from the filesystem.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Loader implements \Twig_LoaderInterface
{
    protected $cache;
    public $application;
    /**
     * Constructor.
     *
     * @param string
     */
    public function __construct($application)
    {
        $this->application = $application;
    }

    /**
     * Gets the source code of a template, given its name.
     *
     * @param  string $name The name of the template to load
     *
     * @return string The template source code
     */
    public function getSource($name)
    {
        return file_get_contents($this->findTemplate($name));
    }

    /**
     * Gets the cache key to use for the cache for a given template name.
     *
     * @param  string $name The name of the template to load
     *
     * @return string The cache key
     */
    public function getCacheKey($name)
    {
        return $this->findTemplate($name);
    }

    /**
     * Returns true if the template is still fresh.
     *
     * @param string    $name The template name
     * @param timestamp $time The last modification time of the cached template
     */
    public function isFresh($name, $time)
    {
        return filemtime($this->findTemplate($name)) <= $time;
    }

    protected function findTemplate($name)
    {
        $parts = explode(':', $name);
        if (count($parts) == 2 && preg_match('/([A-Za-z])/', $parts[0]))
        {
            $name = \shozu\Shozu::getInstance()->project_root.'applications/'.$parts[0].'/views/'.$parts[1];
        }
        else
        {
            $name = \shozu\Shozu::getInstance()->project_root.'applications/'.$this->application.'/views/'.$name;
        }
        // normalize name
        $name = preg_replace('#/{2,}#', '/', strtr($name, '\\', '/'));

        if (isset($this->cache[$name]))
        {
            return $this->cache[$name];
        }

        $this->validateName($name);

        if (is_file($name))
        {
            return $this->cache[$name] = $name;
        }

        throw new \Twig_Error_Loader(sprintf('Unable to find template "%s" .', $name));
    }

    protected function validateName($name)
    {
        if (false !== strpos($name, "\0"))
        {
            throw new \Twig_Error_Loader('A template name cannot contain NUL bytes.');
        }
    }
}

