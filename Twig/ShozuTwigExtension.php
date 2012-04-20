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
     * Returns a list of functions to add to the existing list.
     *
     * @return  array   An array of functions
     */
    public function getFunctions()
    {
        return array(
            'action' => new \Twig_Function_Method($this, 'functionAction')
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

    /**
     * Return rendered view for requested action.
     *
     * @param   string  $action     Fully qualified action name
     * @param   array   $params     Parameters for requested action
     *
     * @return  string  The rendered view
     */
    public function functionAction($action, $params = array())
    {
        list($app, $controller, $action) = explode('/', $action);

        return \shozu\Dispatcher::render($app, $controller, $action, $params);
    }
}
