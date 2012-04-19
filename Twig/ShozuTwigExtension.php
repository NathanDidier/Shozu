<?php

namespace shozu\Twig;

class ShozuTwigExtension extends \Twig_Extension
{
    protected $shozu;

    public function __construct($shozu)
    {
        $this->shozu = $shozu;
    }

    public function getGlobals()
    {
        return array(
            '_s' => $this->shozu
        );
    }

    public function getName()
    {
        return 'shozu';
    }
}
