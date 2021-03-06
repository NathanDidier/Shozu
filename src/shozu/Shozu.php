<?php
namespace shozu;
/**
 * Dependency injection a la Twittee and bootstrap
 *
 * See http://twittee.org/ for dependency injection examples
 */
class Shozu
{
    private static $instance;
    private $store = array();

    public function __set($k, $c)
    {
        $this->store[$k] = $c;
    }

    public function __get($k)
    {
        if (!isset($this->store[$k])) {
            throw new \InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $k));
        }

        if (is_callable($this->store[$k])) {
            $this->store[$k] = $this->store[$k]($this);
        }

        return $this->store[$k];
    }

    public function __isset($k)
    {
        return isset($this->store[$k]);
    }

    /**
     * Merge translation strings with current translations
     *
     * @param array $translations
     */
    public function mergeTranslation(array $translations)
    {
        $this->translations = array_merge($this->translations, $translations);
    }

    /**
     * Generate url for dynamic content.
     *
     * Depending on the url_rewriting setting, you'll want either normal urls or
     * clean urls ("http://www.yoursite.com/?/app/controller/action" or "http://www.yoursite.com/app/controller/action").
     *
     * <code>
     * $url = Shozu::getInstance()->url('app/controller/action');
     * </code>
     *
     * @param  string     $target targeted path to action
     * @param  array|null $params
     * @param  null       $site
     * @return string
     */
    public function url($target, array $params = null, $site = null)
    {
        $cache_key = 'url_'.md5($target.implode('', (array) $params).$site);
        $cache = Cache::getInstance('urls', array('type' => 'array'));
        if (($ret = $cache->fetch($cache_key)) === false) {
            if (substr($target, 0, 1) == '/') {
                $target = substr($target, 1);
            }
            if (($reversed = \shozu\Dispatcher::reverseRoute($target, $params)) !== false) {
                $target = $reversed;
            } elseif (is_array($params)) {
                foreach ($params as $key => $param) {
                    $params[$key] = urlencode($param);
                }
                $target.= '/' . implode('/', $params);
            }
            if (substr($target, 0, 1) == '/') {
                $target = substr($target, 1);
            }
            if ($this->url_rewriting === true) {
                $ret = $this->base_url . $target;
            } else {
                $ret = $this->base_url . '?/' . $target;
            }
            $cache->store($cache_key, $ret);
        }

        return $ret;
    }

    /**
     * Return Shozu default config.
     *
     * List of Shozu config keys (see source for default values): document_root,
     * project_root, benchmark, url_rewriting, use_i18n, default_application,
     * default_controller, default_action, db_dsn, db_user, db_pass, base_url,
     * debug, routes, include_path, error_handler, timezone, session_name,
     * session.
     *
     * @return string
     */
    public function getConfigDefaults()
    {
        return array(
            'document_root'           => function(){return \shozu\Shozu::getInstance()->project_root . 'docroot/';},
            'project_root'            => __DIR__ . '/',
            'benchmark'               => false,
            'url_rewriting'           => function(){if (!defined('SHOZU_URL_REWRITING')) {define('SHOZU_URL_REWRITING', in_array('mod_rewrite', apache_get_modules()));}return SHOZU_URL_REWRITING;},
            'use_i18n'                => false,
            'translations'            => array() ,
            'default_application'     => 'welcome',
            'default_controller'      => 'index',
            'default_action'          => 'index',
            'db_dsn'                  => 'mysql:host=localhost;dbname=test',
            'db_user'                 => 'root',
            'db_pass'                 => '',
            'db_log'                  => false,
            'init_db'                 => false,
            'base_url'                => function() {
                return \shozu\Shozu::getInstance()->getBaseURL();
            },
            'debug'                   => false,
            'routes'                  => array() ,
            'include_path'            => explode(PATH_SEPARATOR, get_include_path()) ,
            'error_handler'           => '',
            'timezone'                => 'Europe/Paris',
            'session_name'            => 'app_session',
            'session'                 => function(){return \shozu\Session::getInstance(\shozu\Shozu::getInstance()->session_name);},
            'redbean_start'           => false,
            'redbean_freeze'          => false,
            'cli'                     => php_sapi_name() == 'cli' ? true : false,
            'enable_default_routing'  => true,
            'observers'               => array(),
            'registered_applications' => array(),
            'twig_extensions'         => array(),
            'twig_cache_path'         => function() {
                return join(DIRECTORY_SEPARATOR, array(
                    sys_get_temp_dir(),
                    sha1(\shozu\Shozu::getInstance()->project_root)
                ));
            }
        );
    }

    /**
     * Return Shozu default config.
     *
     * List of Shozu config keys (see source for default values): document_root,
     * project_root, benchmark, url_rewriting, use_i18n, default_application,
     * default_controller, default_action, db_dsn, db_user, db_pass, base_url,
     * debug, routes, include_path, error_handler, timezone, session_name,
     * session.
     *
     * @param  array  $override configuration
     * @return string
     */
    public function setConfig($override = array())
    {
        $config = array_merge($this->getConfigDefaults(), $override);
        foreach ($config as $key => $val) {
            $this->__set($key, $val);
        }
    }

    /**
     * Bootstraps application, dispatch query.
     *
     * The Shozu instance acts like a minimalistic dependency injection controller
     * a la Twittee (http://twittee.org/), so you can add config values or even closures.
     *
     * <code>
     * Shozu::getInstance()->handle(array(
     *           'url_rewriting'         => false, // override shozu config
     *           'your_own_config_param' => 'some value', // add your own config
     *           'your_dependency'       => function(){ return new Foo;} // use closures
     *
     * ));
     * </code>
     *
     * @param array $override configuration
     */
    public function handle($override = array())
    {
        $this->setConfig($override);

        date_default_timezone_set($this->timezone);
        if (!$this->cli) {
            set_exception_handler(array(
                '\shozu\Shozu',
                'handleError'
            ));
        }
        try {
            if ($this->getCache) {
                View::setCache($this->getCache);
            }
        } catch (\Exception $e) {

        }
        if ($this->use_i18n) {
            $l = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : 'en';
            $lang = explode(',', $l);
            $lang = strtolower(substr(chop($lang[0]) , 0, 2));
            $this->lang = $lang;
            $langFile = $this->project_root . 'lang' . DIRECTORY_SEPARATOR . $this->lang . '.php';
            if (is_file($langFile)) {
                include ($langFile);
            } else {
                $this->translations = array();
            }
        }
        set_include_path(implode(PATH_SEPARATOR, array_unique(array_merge(array(
            '.',
            $this->project_root . 'applications',
            $this->project_root . 'lib'
        ) , $this->include_path))));
        if ($this->benchmark) {
            \shozu\Benchmark::enable();
        }
        if ($this->redbean_start) {
            $this->redbean_toolbox = \RedBean_Setup::kickstart(
                $this->db_dsn, $this->db_user, $this->db_pass, $this->redbean_freeze);
        }
        if ($this->init_db) {
            \shozu\DB::getInstance('default', $this->db_dsn, $this->db_user, $this->db_pass);
            if ($this->db_log) {
                \shozu\DB::getInstance('default')->enableLog();
            }
        }
        foreach ($this->registered_applications as $app) {
            $app_class = trim($app).'\Application';
            $app_routes = $app_class::getRoutes();
            if (is_array($app_routes)) {
                $this->routes = array_merge($this->routes, $app_routes);
            }
            $app_observers = $app_class::getObservers();
            if (is_array($app_observers)) {
                $this->registerObservers($app_observers);
            }
            if ($this->use_i18n) {
                $this->translations = array_merge($this->translations, $app_class::getTranslations($this->lang));
            }
        }

        $this->registerObservers($this->observers);

        \shozu\Dispatcher::addRoute($this->routes);
        if (!$this->enable_default_routing) {
            \shozu\Dispatcher::disableDefaultRouting();
        }
        \shozu\Observer::notify('shozu.is_ready_to_dispatch');
        if (!$this->cli) {
            $this->dispatch();
        }
        global $argv;
        if (isset($argv[1])) {
            \shozu\Dispatcher::dispatch($argv[1]);
        }
    }

    public function getBaseURL()
    {
        return sprintf('%s%s%s',
            $this->getScheme(),
            $this->getHost(),
            '/'
        );
    }

    public function getHost()
    {
        return isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null;
    }

    public function getScheme()
    {
        if (isset($this->scheme)) {
            return $this->scheme;
        }
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
            return 'https://';
        }

        return 'http://';
    }

    private function registerObservers(array $observers_array)
    {
        foreach ($observers_array as $event => $observers) {
            foreach ($observers as $observer) {
                \shozu\Observer::observe($event, $observer);
            }
        }
    }

    private function dispatch()
    {
        \shozu\Benchmark::start('dispatch');
        \shozu\Observer::notify('shozu.dispatch');
        \shozu\Dispatcher::dispatch();
    }

    public static function handleError(\Exception $e)
    {
        if (!($e instanceof FlowException)){
            error_log(sprintf("%s: %s\n%s",
                get_class($e),
                $e->getMessage(),
                $e->getTraceAsString()
            ));
        }

        if (\shozu\Shozu::getInstance()->error_handler != '') {
            list($application, $controller, $action) = explode('/', \shozu\Shozu::getInstance()->error_handler);
            die(\shozu\Dispatcher::render($application, $controller, $action, array($e)));
        }
        if (\shozu\Shozu::getInstance()->debug === true) {
            if (!headers_sent()) {
                header('content-type: text/plain');
            } else {
                echo '<pre>';
            }
            die($e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
        } else {
            if ($e->getCode() == '404') {
                header('content-type: text/plain');
                header("HTTP/1.0 404 Not Found");
                die('file not found.');
            } else {
                header('content-type: text/plain');
                header("HTTP/1.0 500 Internal Error");
                die('internal error.');
            }
        }
    }

    /**
     * Get Shozu instance
     *
     * @return Shozu
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new \shozu\Shozu;
        }

        return self::$instance;
    }

    /**
     * Get Shozu instance.
     *
     * Just a short alias for Shozu::getInstance()
     *
     * @return Shozu
     */
    public static function _()
    {
        return self::getInstance();
    }

    /**
     * Default autoloader
     *
     * Assumes namespaces map to file system: \myns\myClass => /myns/myClass.php
     *
     * @param string $class class fully qualified name
     * @return mixed
     */
    public static function autoload($class)
    {
        if (substr($class, 0, 1) == '\\') {
            $class = substr($class, 1);
        }
        $classFile = str_replace(array('_', '\\'), array('/', '/'), $class) . '.php';
        $result = include ($classFile);

        return $result;
    }

    /**
     * Set a shared service, using a closure for lazy instanciation.
     *
     * <code>
     * $container->setShared('my_service', function(){return new myService;});
     * </code>
     *
     * @staticvar closure $object
     * @param  string   $key
     * @param  callable  $shared
     * @return Shozu
     */
    public function setShared($key, $shared)
    {
        $this->store[$key] = function ($container) use ($shared) {
            static $object;
            if (is_null($object)) {
                $object = $shared($container);
            }

            return $object;
        };

        return $this;
    }
}
