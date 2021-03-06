<?php
namespace shozu;
/**
 * Dispatcher
 *
 * @package MVC
 */
final class Dispatcher
{
    private static $routes = array();
    private static $params = array();
    private static $status = array();
    private static $requested_url = '';
    private static $enable_default_routing = true;

    /**
     * Add route
     *
     * '/' => 'page/index',
     * '/about' => 'page/about,
     * '/blog/:num' => 'blog/post/$1',
     * '/blog/:num/comment/:num/delete' => 'blog/deleteComment/$1/$2'
     *
     * @static
     * @param $route
     * @param null $destination
     */
    public static function addRoute($route, $destination = null)
    {
        if ($destination != null && !is_array($route)) {
            $route = array(
                $route => $destination
            );
        }
        self::$routes = array_merge(self::$routes, $route);
    }

    /**
     * @static
     * @param $url
     * @return array
     */
    public static function splitUrl($url)
    {
        return preg_split('/\//', $url, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Tries to reverse a route
     *
     * <code>
     * \Dispatcher::addRoute('blog/post/id/:num/slug/:any', 'blog/post/show/$1/$2');
     * \Dispatcher::addRoute('contenu-statique.html', 'static/page/show/789');
     * echo \Shozu::getInstance()->url('blog/post/show', array(321, 'un-slug-bien-gras'));
     * echo "\n" . \Shozu::getInstance()->url('journal/billet/voir', array(321));
     * echo "\n" . \Shozu::getInstance()->url('static/page/show/789');
     * </code>
     *
     * @param  string $dest   destination path (app/controller/action)
     * @param  array  $params action parameters
     * @return mixed  reversed route or false
     */
    public static function reverseRoute($dest, array $params = null)
    {
        if (substr($dest, 0, 1) == '/') {
            $dest = substr($dest, -1);
        }
        foreach (self::$routes as $route => $destination) {
            if (strstr($destination, $dest)) {
                if (is_array($params)) {
                    if (strpos($route, ':') !== false) {
                        $route = str_replace(':any', '(.+)', str_replace(':num', '([0-9]+)', $route));
                    }
                    $route = str_replace('(.+)', ':replace:', str_replace('([0-9]+)', ':replace:', $route));
                    $parts = explode(':replace:', $route);
                    $route = '';
                    foreach ($parts as $key => $part) {
                        $route.= $part . (isset($params[$key]) ? $params[$key] : '');
                    }
                }

                return $route;
            }
        }

        return false;
    }

    /**
     * @static
     * @param  null       $requested_url
     * @param  null       $default
     * @return mixed
     * @throws \Exception
     */
    public static function dispatch($requested_url = null, $default = null)
    {
        //Flash::init();
        // If no url passed, we will get the first key from the _GET array
        // that way, index.php?/application/controller/action/var1&email=example@example.com
        // requested_url will be equal to: /application/controller/action/var1
        //
        //
        if ($requested_url === null) {
            $url_rewrite = Shozu::getInstance()->url_rewriting;
            if ($url_rewrite && isset($_SERVER['REDIRECT_URL'])) {
                $requested_url = $_SERVER['REDIRECT_URL'];
            } elseif ($url_rewrite && isset($_SERVER['REQUEST_URI'])) {
                $pos = strpos($_SERVER['REQUEST_URI'], '?');
                if ($pos !== false) {
                    $requested_url = substr($_SERVER['REQUEST_URI'], 0, $pos);
                } else {
                    $requested_url = $_SERVER['REQUEST_URI'];
                }
            } else {
                $pos = strpos($_SERVER['QUERY_STRING'], '&');
                if ($pos !== false) {
                    $requested_url = substr($_SERVER['QUERY_STRING'], 0, $pos);
                } else {
                    $requested_url = $_SERVER['QUERY_STRING'];
                }
            }
        }
        // If no URL is requested (due to someone accessing admin section for the first time)
        // AND $default is setAllow for a default tab
        if ($requested_url == null && $default != null) {
            $requested_url = $default;
        }
        // Requested url MUST start with a slash (for route convention)
        if (strpos($requested_url, '/') !== 0) {
            $requested_url = '/' . $requested_url;
        }

        Observer::notify('shozu.dispatch.has_requested_url', $requested_url);

        self::$requested_url = $requested_url;
        // This is only trace for debugging
        self::$status['requested_url'] = $requested_url;
        // Make the first split of the current requested_url
        self::$params = self::splitUrl($requested_url);
        // Do we even have any custom routing to deal with?
        if (count(self::$routes) === 0 && self::$enable_default_routing) {
            return self::executeAction(self::getApplication() , self::getController() , self::getAction() , self::getParams());
        }
        // Is there a literal match? If so we're done
        if (isset(self::$routes[$requested_url])) {
            self::$params = self::splitUrl(self::$routes[$requested_url]);

            return self::executeAction(self::getApplication() , self::getController() , self::getAction() , self::getParams());
        }
        // Loop through the route array looking for wildcards
        $is_custom_routing = false;
        $we_have_a_route = false;
        foreach (self::$routes as $route => $uri) {
            // Convert wildcards to regex
            if (strpos($route, ':') !== false) {
                $route = str_replace(':any', '(.+)', str_replace(':num', '([0-9]+)', $route));
            }
            // Does the regex match?
            if (preg_match('#^' . $route . '$#', $requested_url)) {
                // Do we have a back-reference?
                if (strpos($uri, '$') !== false && strpos($route, '(') !== false) {
                    $uri = preg_replace('#^' . $route . '$#', $uri, $requested_url);
                }
                self::$params = self::splitUrl($uri);
                $is_custom_routing = true;
                // We found it, so we can break the loop now!
                $we_have_a_route = true;
                break;
            }
        }
        if (!self::$enable_default_routing && !$is_custom_routing) {
            throw new \Exception('no default routing allowed', 404);
        }

        if (!$we_have_a_route) {
            Observer::notify('shozu.dispatch.has_no_route', $requested_url);
        }

        return self::executeAction(self::getApplication() , self::getController() , self::getAction() , self::getParams());
    }

    public static function disableDefaultRouting()
    {
        self::$enable_default_routing = false;
    }

    public static function enableDefaultRouting()
    {
        self::$enable_default_routing = true;
    }

    public static function getCurrentUrl()
    {
        return self::$requested_url;
    }

    public static function getApplication()
    {
        return isset(self::$params[0]) ? self::$params[0] : Shozu::getInstance()->default_application;
    }

    public static function getController()
    {
        return isset(self::$params[1]) ? self::$params[1] : Shozu::getInstance()->default_controller;
    }

    public static function getAction()
    {
        return isset(self::$params[2]) ? self::$params[2] : Shozu::getInstance()->default_action;
    }

    public static function getParams()
    {
        return array_slice(self::$params, 3);
    }

    public static function getStatus($key = null)
    {
        return ($key === null) ? self::$status : (isset(self::$status[$key]) ? self::$status[$key] : null);
    }

    /**
     * @static
     * @param $application
     * @param $controller
     * @param $action
     * @param  array|null $params
     * @param  bool       $layoutEnabled
     * @return mixed
     * @throws \Exception
     */
    public static function executeAction($application, $controller, $action, array $params = null, $layoutEnabled = false)
    {
        $params = (array) $params;
        self::$status['application'] = $application;
        self::$status['controller'] = $controller;
        self::$status['action'] = $action;
        self::$status['params'] = implode(', ', array_filter($params, 'is_scalar'));

        self::initApplication($application);

        $controller_class = self::$status['application'] . '\\controllers\\' . Inflector::camelize($controller);
        $class_exists = class_exists($controller_class, true);
        if ($class_exists) {
            $controller = new $controller_class;
            if (!$controller instanceof Controller) {
                throw new \Exception("Class '{$controller_class}' does not extends Controller class!");
            }
            // Execute the action
            return $controller->execute($action, $params, $layoutEnabled);
        } else {
            throw new FlowException('not found', 404);
        }
    }

    public static function render($application, $controller, $action, $params = array() , $layoutEnabled = false)
    {
        ob_start();
        self::executeAction($application, $controller, $action, $params, $layoutEnabled);
        $content = ob_get_clean();

        return $content;
    }

    /*
     * @param   $application    An application name
     */
    public static function initApplication($application)
    {
        $init_filepath = join(DIRECTORY_SEPARATOR, array(
            Shozu::getInstance()->project_root,
            'applications',
            $application,
            'AppInit.php'
        ));

        if (!is_readable($init_filepath)) {
            return;
        }

        require_once $init_filepath;
    }
}
