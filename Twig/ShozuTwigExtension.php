<?php

namespace shozu\Twig;

class ShozuTwigExtension extends \Twig_Extension
{
    protected $shozu;

    /**
     * @param   \shozu\Shozu    $shozu  Shozu instance
     */
    public function __construct($shozu)
    {
        $this->shozu = $shozu;
    }

    /**
     * Returns a list of global variables to add to the existing list.
     *
     * @return  array   An array of global variables
     */
    public function getGlobals()
    {
        return array(
            '_s' => $this->shozu
        );
    }

    /**
     * Returns the name of the extension.
     *
     * @return  string  The extension name
     */
    public function getName()
    {
        return 'shozu';
    }
}
