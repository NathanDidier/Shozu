<?php
namespace shozu;
/**
 * Action controller
 *
 * @package MVC
 */
abstract class Controller
{
    public $application;
    protected $layout = false;
    protected $layout_vars = array();
    /**
     * @var \Twig_Environment
     */
    protected $twig;
    protected $request = null;

    /**
     * New Controller
     *
     * @internal param string $application application name
     */
    public function __construct()
    {
        $parts = explode('\\', get_called_class());
        $this->application = $parts[0];
    }

    /**
     * Execute action
     *
     * @param  string     $action        name
     * @param  array      $params
     * @param  boolean    $layoutEnabled
     * @throws \Exception
     * @return mixed
     */
    public function execute($action, $params, $layoutEnabled = true)
    {
        $method = $action . 'Action';
        // it's a private method of the class or action is not a method of the class
        if (substr($method, 0, 1) == '_' || !method_exists($this, $method)) {
            throw new \Exception("Action '{$action}' is not valid!", 404);
        }

        return call_user_func_array(array($this, $method), $params);
    }

    /**
     * Set layout file
     *
     * @param string $layout
     */
    public function setLayout($layout)
    {
        if (substr($layout, -4) != '.php') {
            $layout = Shozu::getInstance()->project_root . 'applications'
                                          . DIRECTORY_SEPARATOR . $this->application
                                          . DIRECTORY_SEPARATOR . 'views'
                                          . DIRECTORY_SEPARATOR . $layout . '.php';
        }
        $this->layout = $layout;
    }

    /**
     * Disable layout
     */
    public function disableLayout()
    {
        $this->layout = false;
    }

    /**
     * Assign variable to layout
     *
     * @param string $var   var name
     * @param mixed  $value
     */
    public function assignToLayout($var, $value)
    {
        if (is_array($var)) {
            array_merge($this->layout_vars, $var);
        } else {
            $this->layout_vars[$var] = $value;
        }
    }

    /**
     * Render a View
     *
     * <code>
     * // render view scripts in current application views path
     * $rendered = $controller->render('index');
     * $rendered = $controller->render('user/form');
     * // render view script with absolute path
     * $rendered = $controller->render('/var/www/templates/index.php');
     * </code>
     *
     * @param string view name or path to view script
     * @param  array $vars assigned variables
     * @return View
     */
    public function render($view, $vars = array())
    {
        $base_name = basename($view);
        if (strstr($base_name,'.twig')) {
            foreach ($this->layout_vars as $k => $v) {
                $vars['_'.$k] = $v;
            }
            $twig = $this->getTwig();
            $twig_template = $twig->loadTemplate($view);
            Observer::notify('shozu.controller.before_twig_render', $twig, $twig_template);
            return $twig_template->render($vars);
        }
        if (substr($view, -4) != '.php') {
            $view = Shozu::getInstance()->project_root
                . 'applications' . DIRECTORY_SEPARATOR
                . $this->application . DIRECTORY_SEPARATOR
                . 'views' . DIRECTORY_SEPARATOR . $view . '.php';
        }
        if ($this->layout) {
            $this->layout_vars['content_for_layout'] = new \shozu\View($view, $vars);

            return new \shozu\View($this->layout, $this->layout_vars);
        } else {
            return new \shozu\View($view, $vars);
        }
    }

    /**
     * @return \Twig_Environment
     */
    public function getTwig()
    {
        if (!$this->twig) {
            $shozu = Shozu::getInstance();
            $cache_path = $shozu->twig_cache_path;
            if (!is_dir($cache_path)) {
                mkdir($cache_path);
            }
            $this->twig = new \Twig_Environment(
                new \shozu\Twig\Loader($this->application),
                array(
                    'cache' => $cache_path,
                    'debug' => $shozu->debug
                )
            );
            $this->twig->addExtension(
                new \shozu\Twig\ShozuTwigExtension($shozu)
            );
            if (!is_null(Shozu::getInstance()->twig_cache_extension)) {
                $this->twig->addExtension(Shozu::getInstance()->twig_cache_extension);
            }
            if (!is_null(Shozu::getInstance()->twig_extensions)) {
                foreach (Shozu::getInstance()->twig_extensions as $extension) {
                    $this->twig->addExtension($extension);
                }
            }
        }

        return $this->twig;
    }

    /**
     * display (echoes) rendered view
     *
     * @param string  $view view name
     * @param array   $vars assigned vars
     * @param boolean $exit die after echo
     */
    public function display($view, $vars = array(), $exit = false)
    {
        echo $this->render($view, $vars);
        if ($exit) {
            exit;
        }
    }

    /**
     * HTTP 301 redirect
     *
     * <code>
     * // redirect to website
     * $c->redirect('http://www.desfrenes.com');
     * // redirect to shozu url
     * $c->redirect('app/controller/action'):
     * </code>
     *
     * @param string
     * @param array|null $params
     */
    public function redirect($route, array $params = null)
    {
        if (substr($route, 0, 4) != 'http') {
            $route = Shozu::getInstance()->url($route, $params);
        }
        header('Location: ' . $route);
        die;
    }

    /**
     * Get request param value from _GET or _POST.
     *
     * Will return default value if param doesn't exist.
     *
     * @param  string     $name    param name
     * @param  mixed      $default default value
     * @return mixed|null
     */
    public function getParam($name, $default = null)
    {
        if (isset($_POST[$name])) {
            return $_POST[$name];
        }
        if (isset($_GET[$name])) {
            return $_GET[$name];
        }

        return $default;
    }

    /**
     * Get request method ("post" or "get")
     *
     * @return string
     */
    public function getRequestMethod()
    {
        if (count($_POST) > 0) {
            return 'post';
        }

        return 'get';
    }

    /**
     * Get Request object.
     *
     * @return Request object
     */
    public function getRequest()
    {
        if (is_null($this->request)) {
            $this->request = new Request();
        }

        return $this->request;
    }

    /**
     * Very basic flood protection based on client IP. Don't trust this !
     * This might suffice for common vandalism, but you'd better implement
     * a proven solution way down your stack (the sooner, the better).
     *
     * @param string $resource_name
     * @param $requests
     * @param integer    $time  time period (in seconds)
     * @param null|Cache $cache
     * @internal param int $max_requests max number of requests
     * @internal param \shozu\cache $Cache backend
     */
    protected function floodLimit($resource_name, $requests, $time = 60, Cache $cache = null)
    {
        AntiFlood::limit($resource_name, $requests, $time, $cache);
    }
}
