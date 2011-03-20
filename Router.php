<?php
namespace shozu;

// CORE

interface RequestInterface
{
    /**
     * @return string
     */
    public function getRequestedUrl();

    /**
     * @return \DateTime
     */
    public function getDateTime();

    /**
     * @return string
     */
    public function getCookieVar($cookie_name);

    /**
     * @return string
     */
    public function getPostVar($post_var_name);

    /**
     * @return string
     */
    public function getGetVar($get_var_name);

    /**
     * @return string
     */
    public function getServerVar($server_var_name);

    /**
     * @return string
     */
    public function getSessionVar($session_var_name);
    /**
     *
     * @param string $uploaded_file_name
     * @return Array
     */
    public function getUploadedFile($uploaded_file_name);

    /**
     * @return string
     */
    public function getRequestMethod();

    /**
     * @return string
     */
    public function getBody();

    /**
     *
     * @param string $param_name
     * @param string $param_value
     */
    public function setParam($param_name, $param_value);

    /**
     *
     * @param string $param_name
     */
    public function getParam($param_name);
}

interface ResponseInterface
{
    /**
     *
     * @param string $cookie_var_name
     * @param string $cookie_var_value
     */
    public function setCookieVar($cookie_var_name, $cookie_var_value,  $expires = 0);

    /**
     *
     * @param string $session_var_name
     * @param mixed $session_var_value
     */
    public function setSessionVar($session_var_name, $session_var_value);

    public function send();
}

interface ActionInterface
{
    /**
     * @param RequestInterface $request
     */
    public function __construct(RequestInterface $request);

    /**
     * @return ResponseInterface
     */
    public function getResponse();

    /**
     * @return RequestInterface
     */
    public function getRequest();
}

class Exception extends \Exception
{

}

class HTTPException extends Exception
{

}

class RouterException extends Exception{}

class Router
{
    private $routes = array();
    private $event_dispatcher;
    private static $instance;

    /**
     *
     * @return Router
     */
    public static function getInstance()
    {
        if(is_null(self::$instance))
        {
            self::$instance = new self;
        }
        return self::$instance;
    }

    private function  __construct()
    {
        spl_autoload_register(array(get_called_class(),'autoload'));
    }


    private function notify($event)
    {
        if(!is_null($this->event_dispatcher))
        {
            call_user_func_array(array($this->event_dispatcher, 'notify'), func_get_args());
        }
    }

    public function setEventDispatcher(EventDispatcherInterface $event_dispatcher)
    {
        $this->event_dispatcher = $event_dispatcher;
        return $this;
    }

    /**
     *
     * @param string $route
     * @param string $action_name
     * @return Router
     */
    public function registerAction($route, $action_name)
    {
        $parts = explode(' ', $route);
        if(!in_array($parts[0], array('GET','POST','DELETE','PUT')))
        {
            throw new RouterException('unknown request method');
        }
        foreach($parts as $k=>$v)
        {
            $v = trim($v);
            if(empty($v))
            {
                unset($parts[$k]);
            }
        }
        $route = implode(' ', $parts);
        $this->routes[$route] = $action_name;
        return $this;
    }

    /**
     *
     * @param string $action_name
     * @param RequestInterface $request
     * @param array $params
     */
    public function executeAction($action_name, RequestInterface $request, Array $params = array())
    {
        foreach($params as $param_name => $param_value)
        {
            $request->setParam($param_name, $param_value);
        }
        $action = new $action_name($request);
        if(!($action instanceof ActionInterface))
        {
            throw new RouterException('Not an Action');
        }
        $this->notify('router.has_action', $action);
        $response = $action->getResponse();
        if(!($response instanceof ResponseInterface))
        {
            throw new RouterException('Not a Response');
        }
        $this->notify('router.has_response', $response);
        $response->send();
    }

    /**
     *
     * @param RequestInterface $request
     */
    public function dispatch(RequestInterface $request)
    {
        $run_this = $this->findAction($request);
        $this->executeAction($run_this['action'], $request, $run_this['params']);
    }

    /**
     *
     * @param string $action_name
     * @param array $params
     * @return string
     */
    public function getUrl($action_name, array $params = array())
    {
        foreach($this->routes as $route => $a_name)
        {
            if($action_name == $a_name)
            {
                foreach($params as $k => $v)
                {
                    $route = str_replace('<'.$k.'>', $v, $route);
                }
                if(strpos($route, '<'))
                {
                    throw new RouterException('Missing parameter');
                }
                // remove leading http verb
                $parts = explode(' ', $route);
                if(in_array($parts[0], array('GET','POST','DELETE','OUT')))
                {
                    unset($parts[0]);
                }
                return trim(implode('', $parts));
            }
        }
        throw new RouterException('Could not generate URL');
    }

    private function findAction(RequestInterface $request)
    {
        $requested_url = $this->getRequestedRoute($request);
        $this->notify('has_uri');
        $action = '';
        $params = array();
        if(isset($this->routes[$requested_url]))
        {
            $action = $this->routes[$requested_url];
        }
        else
        {
            foreach($this->routes as $route => $action_name)
            {
                $regex = '#^' . str_replace('/','\/',preg_replace('/<([a-z0-9]+)>/i', '([a-zA-Z0-9\-\._]+)', $route)) . '$#';
                if (preg_match($regex, $requested_url))
                {
                    $action = $action_name;
                    $params = $this->extractParams($route, $requested_url, $regex);
                    break;
                }
            }
        }
        if(empty($action))
        {
            throw new RouterException('no such page', 404);
        }
        if(!class_exists($action))
        {
            throw new RouterException('action unavailable', 500);
        }
        return array('action' => $action, 'params' => $params);
    }

    private function extractParams($route, $requested_url, $regex)
    {
        $param_names = array();
        preg_match_all('/<([a-z0-9]+)>/i', $route, $param_names);
        if(count($param_names) === 2)
        {
            $param_names = $param_names[1];
        }
        $param_values = array();
        preg_match_all($regex, $requested_url, $param_values, \PREG_SET_ORDER);
        if(count($param_values))
        {
            $param_values = array_pop($param_values);
            if(count($param_values) && $param_values[0] == $requested_url)
            {
                array_shift($param_values);
            }
        }
        if(count($param_names) === count($param_values))
        {
            return array_combine($param_names, $param_values);
        }
        return array();
    }

    private function getRequestedRoute(RequestInterface $request)
    {
        $requested_url = $request->getRequestedUrl();
        $pos = strpos($requested_url, '&');
        if ($pos !== false)
        {
            $requested_url = substr($requested_url, 0, $pos);
        }
        if (strpos($requested_url, '/') !== 0)
        {
            $requested_url = '/' . $requested_url;
        }
        $parts = explode('?', $requested_url);
        $requested_url = $parts[0];
        $requested_url = $request->getRequestMethod() . ' ' . $requested_url;
        return  $requested_url;
    }

    /**
     *
     * @param string $class
     * @return bool
     */
    public static function autoload($class)
    {
        if (substr($class, 0, 1) == '\\')
        {
            $class = substr($class, 1);
        }
        $classFile = str_replace(array('_', '\\'), array(\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR), $class) . '.php';
        $old = ini_set('error_reporting', 0);
        $result = include ($classFile);
        ini_set('error_reporting', $old);
        return $result;
    }
}

interface EventDispatcherInterface
{
    public function attach($event, $callback);
    public function detach($event, $callback);
    public function notify($event);
}


// OPTIONAL

class EventDispatcher implements EventDispatcherInterface
{
    private $events = array(); // events callback
    private static $instance;

    private function  __construct()
    {

    }
    
    /**
     *
     * @return EventDispatcher
     */
    public static function getInstance()
    {
        if(is_null(self::$instance))
        {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * Observe event
     *
     * <code>
     * EventDispatcher::observe('system.shutdown', array('Profiler', 'display'));
     * </code>
     *
     * @param string
     * @param mixed
     * @return EventDispatcher
     */
    public function attach($event, $callback)
    {
        if (!isset($this->events[$event]))
        {
            $this->events[$event] = array();
        }
        $this->events[$event][] = $callback;
        return $this;
    }


    /**
     *
     * @param string $name
     * @param callback $callback
     * @return EventDispatcher
     */
    public function detach($event, $callback=false)
    {
        if (!$callback)
        {
            $this->events[$event] = array();
        }
        else if(isset($this->events[$event]))
        {
            foreach ($this->events[$event] as $i => $event_callback)
            {
                if ($callback === $event_callback)
                {
                    unset($this->events[$event][$i]);
                }
            }
        }
        return $this;
    }

    /**
     *
     * @param string $name
     * @return array
     */
    public function get($event)
    {
        return empty($this->events[$event]) ? array(): $this->events[$event];
    }

    /**
     * Notify event
     *
     * <code>
     * EventDispatcher::notify('system.execute');
     * </code>
     *
     * @param string
     */
    public function notify($event)
    {
        // removing event name from the arguments
        $args = func_num_args() > 1 ? array_slice(func_get_args(), 1): array();
        foreach ($this->get($event) as $callback)
        {
            if(is_callable($callback))
            {
                call_user_func_array($callback, $args);
            }
        }
    }
}


abstract class ResponseAbstract implements ResponseInterface
{
    private $headers = array();
    private $body = '';


    public function sendHeaders()
    {
        foreach ($this->headers as $header_name => $header_value)
        {
            header($header_name . ': ' . $header_value, true);
        }
    }

    /**
     *
     * @param string $header_name
     * @param string $header_value
     * @return HTMLResponse
     */
    public function setHeader($header_name, $header_value)
    {
        $this->headers[$header_name] = $header_value;
        return $this;
    }

    /**
     *
     * @param string $body
     * @return HTMLResponse
     */
    public function setBody($body)
    {
        $this->body = $body;
        return $this;
    }

    /**
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     *
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }
    
    /**
     *
     * @param string $cookie_var_name
     * @param string $cookie_var_value
     * @param integer $expires
     */
    public function setCookieVar($cookie_var_name, $cookie_var_value, $expires = 0)
    {
        setcookie($cookie_var_name, $cookie_var_value, $expires);
    }

    /**
     *
     * @param string $session_var_name
     * @param string $session_var_value
     */
    public function setSessionVar($session_var_name, $session_var_value)
    {
        if(!isset($_SESSION))
        {
            session_start();
        }
        $_SESSION[$session_var_name] = $session_var_value;
    }
}

abstract class ActionAbstract implements ActionInterface
{
    protected $request;

    /**
     *
     * @param RequestInterface $request
     */
    public function __construct(RequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     *
     * @return RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }
}

class Request implements RequestInterface
{
    private $constructed_at;
    private $constructed_at_datetime;
    private $params;

    public function __construct()
    {
        $this->constructed_at = time();
    }


    /**
     * @return string
     */
    public function getRequestedUrl()
    {
        return(isset($_SERVER['REDIRECT_URL']) ? $_SERVER['REDIRECT_URL'] : $_SERVER['REQUEST_URI']);
    }

    /**
     * @return DateTime
     */
    public function getDateTime()
    {
        if(is_null($this->constructed_at_datetime))
        {
            $timestamp = $this->getServerVar('REQUEST_TIME') ?: $this->constructed_at;

            $this->constructed_at_datetime = \DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d H:i:s', $timestamp));
        }
        return $this->constructed_at_datetime;
    }

    /**
     *
     * @return string
     */
    public function getRequestMethod()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     *
     * @param string $server_var_name
     * @return string
     */
    public function getServerVar($server_var_name)
    {
        if(isset($_SERVER[$server_var_name]))
        {
            return $_SERVER[$server_var_name];
        }
        return;
    }

    /**
     *
     * @param string $server_var_name
     * @return string
     */
    public function getCookieVar($cookie_name)
    {
        if(isset($_COOKIE[$cookie_name]))
        {
            return $_COOKIE[$cookie_name];
        }
        return;
    }

    /**
     *
     * @param string $server_var_name
     * @return string
     */
    public function getPostVar($post_var_name)
    {
        if(isset($_POST[$post_var_name]))
        {
            return $_POST[$post_var_name];
        }
        return;
    }

    /**
     *
     * @param string $server_var_name
     * @return string
     */
    public function getSessionVar($session_var_name)
    {
        if(!isset($_SESSION))
        {
            session_start();
        }
        if(isset($_SESSION[$session_var_name]))
        {
            return $_SESSION[$session_var_name];
        }
        return;
    }

    /**
     *
     * @param string $server_var_name
     * @return string
     */
    public function getGetVar($get_var_name)
    {
        if(isset($_GET[$get_var_name]))
        {
            return $_GET[$get_var_name];
        }
        return;
    }

    /**
     *
     * @return string
     */
    public function getBody()
    {
        return file_get_contents('php://input');
    }

    /**
     *
     * @param string $uploaded_file_name
     * @return array
     */
    public function getUploadedFile($uploaded_file_name)
    {
        if(isset($_FILES[$uploaded_file_name]))
        {
            $_FILES[$uploaded_file_name];
        }
        return;
    }

    /**
     *
     * @param string $param_name
     * @param string $param_value
     * @return Request
     */
    public function setParam($param_name, $param_value)
    {
        $this->params[$param_name] = $param_value;
        return $this;
    }

    public function getParam($param_name)
    {
        if(isset($this->params[$param_name]))
        {
            return $this->params[$param_name];
        }
        return;
    }
}

class JSONResponse extends ResponseAbstract
{
    private $data;

    /**
     *
     * @param mixed $data
     * @return JSONResponse
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    public function send()
    {
        $this->setHeader('Content-type', 'application/json');
        $this->sendHeaders();
        echo json_encode($this->data);
    }
}

class HTMLResponse extends ResponseAbstract
{
    public function send()
    {
        $this->setHeader('Content-type', 'text/html');
        $this->sendHeaders();
        echo $this->getBody();
    }
}

class TaconiteResponseException extends Exception{
    private $xml;
    public function setXML($xml)
    {
        $this->xml = $xml;
    }
    public function getXML($xml)
    {
        return $this->xml;
    }
}

class TaconiteResponse extends ResponseAbstract
{
    private $debug = false;
    private $content = '';

    public function setDebug($on_off)
    {
        $this->debug = (bool)$on_off;
    }

    /**
     * Appends XHTML content to matching elements
     *
     * @param string $selector Any valid JQuery selector
     * @param string $content Any valid XHTML content
     * @return Taconite Taconite document instance
     */
    public function append($selector, $content)
    {
        $this->elementCommand('append', $selector, $content);
        return $this;
    }
    /**
     * Prepends XHTML content to matching elements
     *
     * @param string $selector Any valid JQuery selector
     * @param string $content Any valid XHTML content
     * @return Taconite Taconite document instance
     */
    public function prepend($selector, $content)
    {
        $this->elementCommand('prepend', $selector, $content);
        return $this;
    }
    /**
     * Puts XHTML content before matching elements
     *
     * @param string $selector Any valid JQuery selector
     * @param string $content Any valid XHTML content
     * @return Taconite Taconite document instance
     */
    public function before($selector, $content)
    {
        $this->elementCommand('before', $selector, $content);
        return $this;
    }
    /**
     * Puts XHTML content after matching elements
     *
     * @param string $selector Any valid JQuery selector
     * @param string $content Any valid XHTML content
     * @return Taconite Taconite document instance
     */
    public function after($selector, $content)
    {
        $this->elementCommand('after', $selector, $content);
        return $this;
    }
    /**
     * Wraps matching elements with given tags.
     *
     * Don't use text in $content
     *
     * @param string $selector Any valid JQuery selector
     * @param string $content Wrapper string
     * @return Taconite Taconite document instance
     */
    public function wrap($selector, $content)
    {
        $this->elementCommand('wrap', $selector, $content);
        return $this;
    }
    /**
     * Replaces matching elements with given content.
     *
     * This is not JQuery-native but a convenience of the Taconite plugin
     *
     * @param string $selector Any valid JQuery selector
     * @param string $content Any valid XHTML content
     * @return Taconite Taconite document instance
     */
    public function replace($selector, $content)
    {
        $this->elementCommand('replace', $selector, $content);
        return $this;
    }
    /**
     * Replaces matching element's content with given content.
     *
     * This is not JQuery-native but a convenience if the Taconite plugin
     *
     * @param string $selector Any valid JQuery selector
     * @param string $content Any valid XHTML content
     * @return Taconite Taconite document instance
     */
    public function replaceContent($selector, $content)
    {
        $this->elementCommand('replaceContent', $selector, $content);
        return $this;
    }
    /**
     * Removes matching elements
     *
     * @param string $selector Any valid JQuery selector
     * @return Taconite Taconite document instance
     */
    public function remove($selector)
    {
        $this->rawCommand('<remove select="' . $selector . '" />');
        return $this;
    }
    /**
     * Shows matching elements
     *
     * @param string $selector Any valid JQuery selector
     * @return Taconite Taconite document instance
     */
    public function show($selector)
    {
        $this->rawCommand('<show select="' . $selector . '" />');
        return $this;
    }
    /**
     * Hides matching elements
     *
     * @param string $selector Any valid JQuery selector
     * @return Taconite Taconite document instance
     */
    public function hide($selector)
    {
        $this->rawCommand('<hide select="' . $selector . '" />');
        return $this;
    }
    /**
     * Remove content from matching elements (JQuery's empty method)
     *
     * @param string $selector Any valid JQuery selector
     * @return Taconite Taconite document instance
     */
    public function removeContent($selector)
    {
        $this->rawCommand('<empty select="' . $selector . '" />');
        return $this;
    }
    /**
     * Adds class to matching elements
     *
     * @param string $class CSS class to add
     * @param string $selector Any valid JQuery selector
     * @return Taconite Taconite document instance
     */
    public function addClass($class, $selector)
    {
        $this->rawCommand('<addClass select="' . $selector . '" arg1="'
            . $class . '" /><addClass select="'
            . $selector . '" value="' . $class . '" />');
        return $this;
    }
    /**
     * Removes class from matching elements
     *
     * @param string $class CSS class to remove
     * @param string $selector Any valid JQuery selector
     * @return Taconite Taconite document instance
     */
    public function removeClass($class, $selector)
    {
        $this->rawCommand('<removeClass select="' . $selector . '" arg1="'
            . $class . '" /><removeClass select="'
            . $selector . '" value="' . $class . '" />');
        return $this;
    }
    /**
     * Toggles a class to matching elements
     *
     * @param string $class CSS class to toggle
     * @param string $selector Any valid JQuery selector
     * @return Taconite Taconite document instance
     */
    public function toggleClass($class, $selector)
    {
        $this->rawCommand('<toggleClass select="' . $selector . '" arg1="'
            . $class . '" /><toggleClass select="'
            . $selector . '" value="' . $class . '" />');
        return $this;
    }
    /**
     * Modifies a css property
     *
     * The taconite plugin requires that you "camelize" all css properties but
     * this will do it for you if forget it.
     *
     * @param string $selector Any valid JQuery selector
     * @param string $property Any CSS property
     * @param string $value CSS value
     * @return Taconite Taconite document instance
     */
    public function css($selector, $property, $value)
    {
        $property = $this->camelize($property);
        $taco = '<css select="' . $selector . '" name="' . $property
            . '" value="' . $value . '" />';
        $this->rawCommand($taco);
        return $this;
    }
    /**
     * Adds Javascript to be evaluated in the global context
     *
     * @param string $script Javascript string
     * @return Taconite Taconite document instance
     */
    public function js($script)
    {
        $taco = '<eval><![CDATA[' . $script . ']]></eval>';
        $this->rawCommand($taco);
        return $this;
    }
    /**
     * Adds an element command, as described in the Taconite plugin docs.
     *
     * @param string $method A JQuery method
     * @param string $selector Any valid JQuery selector
     * @param string $content XHTML content
     * @return Taconite Taconite document instance
     */
    public function elementCommand($method, $selector, $content)
    {
        $taco = '<' . $method . ' select="' . $selector . '">' . $content
            . '</' . $method . '>';
        $this->rawCommand($taco);
        return $this;
    }
    /**
     * Adds a raw Taconite command to the document
     *
     * @param string $command A Taconite command
     * @return Taconite Taconite document instance
     */
    public function rawCommand($command)
    {
        $this->content.= $command;
        return $this;
    }


    /**
     * Javascript alert shortcut
     *
     * @param string
     * @return Taconite Taconite document instance
     */
    public function alert($string)
    {
        $this->js('alert(' . $this->escapeJSArgs($string) . ');');
        return $this;
    }

    /**
     * Javascript status bar shortcut
     *
     * @param string
     * @return Taconite Taconite document instance
     */
    public function status($string)
    {
        $this->js('window.status = ' . $this->escapeJSArgs($string) . ';');
        return $this;
    }

    public function escapeJSArgs($string, $string_delimiter = '"', $add_delimiters = true)
    {
        if ($string_delimiter == '"')
        {
            $string = str_replace(array(
                "\r\n",
                "\n",
                '"'
                ) , array(
                '\n',
                '\n',
                '\"'
                ) , $string);
        }
        elseif ($string_delimiter == "'")
        {
            $string = str_replace(array(
                "\r\n",
                "\n",
                "'"
                ) , array(
                '\n',
                '\n',
                "\'"
                ) , $string);
        }
        else
        {
            trigger_error('delimiter should be single or double quote.', E_USER_ERROR);
        }
        if ($add_delimiters)
        {
            return $string_delimiter . $string . $string_delimiter;
        }
        return $string;
    }

    /**
     * Returns the command document string
     *
     * This method does not perform any syntax check !
     *
     * @return string
     */
    public function getContent()
    {
        return '<taconite>' . $this->content . '</taconite>';
    }

    public function send()
    {
        $trans = array(
            '&nbsp;' => '&#160;'
        );
        $this->content = strtr($this->content, $trans);
        if ($this->isValid())
        {

            $this->setHeader('Expires', '26 Jul 1997 05:00:00 GMT');
            $this->setHeader('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');
            $this->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
            $this->setHeader('Cache-Control', 'pre-check=0, post-check=0, max-age=0');
            $this->setHeader('Pragma', 'no-cache');
            $this->setHeader('Content-type', 'text/xml; charset=UTF-8');
            $this->sendHeaders();
            echo $this->getContent();
        }
        else
        {
            $taconite_exception = new TaconiteResponseException('Document is not valid XML.');
            $taconite_exception->setXML($this->content);
            throw $taconite_exception;
        }
    }

    private function camelize($property)
    {
        $property_chops = explode('-', $property);
        $chops_size = count($property_chops);
        if ($chops_size > 1)
        {
            for ($i = 1;$i < $chops_size;$i++)
            {
                $property_chops[$i] = ucfirst(trim($property_chops[$i]));
            }
            $property = implode('', $property_chops);
        }
        return $property;
    }

    private function isValid()
    {
        $string = $this->getContent();
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->loadXML($string);
        $errors = libxml_get_errors();
        if (empty($errors))
        {
            return true;
        }
        return false;
    }
}

class CookieSessionHandler
{
    private static $obj_session;
    private $expires = 0;
    private $encryption_key;
    private $cookie_domain;
    private $session_id;

    private function __construct($encryption_key, $cookie_domain = '', $session_id = 'omfg')
    {
        $this->encryption_key = $encryption_key;
        $this->cookie_domain = $cookie_domain;
        $this->session_id = $session_id;
        ini_set('session.use_cookies', 0);
        ini_set('session.use_trans_sid', 0);
        session_set_save_handler(array($this, 'open'),
            array($this, 'close'),
            array($this, 'read'),
            array($this, 'write'),
            array($this, 'destroy'),
            array($this, 'gc'));
        register_shutdown_function('session_write_close');
        session_start();
    }

    /**
     *
     * @param integer $stamp
     * @return CookieSessionHandler
     */
    public  function setExpiry($stamp)
    {
        $this->expires = (int)$stamp;
        $_SESSION['sess_exp'] = $this->expires;
        return $this;
    }

    /**
     *
     * @param string $arg_str_save_path
     * @param string $arg_str_session_name
     * @return bool
     */
    public function open($arg_str_save_path, $arg_str_session_name)
    {
        return true;
    }


    /**
     *
     * @return bool
     */
    public function close()
    {
        return true;
    }


    /**
     *
     * @param string $arg_str_session_id
     * @return string
     */
    public function read($arg_str_session_id)
    {
        $arg_str_session_id = $this->session_id;
        if(!isset($_COOKIE[$arg_str_session_id]))
        {
            return '';
        }
        $cypher = $_COOKIE[$arg_str_session_id];
        $plain_text = self::cookieDecrypt($cypher, $this->encryption_key);
        return $plain_text;
    }


    /**
     *
     * @param string $arg_str_session_id
     * @param string $arg_str_session_data
     * @return bool
     */
    public function write($arg_str_session_id, $arg_str_session_data)
    {
        $arg_str_session_id = $this->session_id;
        if(empty($arg_str_session_data))
        {
            return true;
        }
        $cypher = self::cookieCrypt($arg_str_session_data, $this->encryption_key);
        if(strlen($cypher) > 4000)
        {
            throw new Exception('session data overflow');
        }
        if($this->cookie_domain)
        {
            setcookie(session_name(), session_id(), $this->expires, '/', ($this->cookie_domain ? '.' . $this->cookie_domain : NULL));
        }
        setcookie($arg_str_session_id, $cypher, $this->expires, '/', ($this->cookie_domain ? '.' . $this->cookie_domain : NULL));
        return true;
    }


    /**
     *
     * @param string $arg_str_session_id
     * @return bool
     */
    public function destroy($arg_str_session_id)
    {
        $arg_str_session_id = $this->session_id;
        setcookie($arg_str_session_id, '');
        return true;
    }


    /**
     *
     * @param string $arg_int_next_lifetime
     * @return bool
     */
    public function gc($arg_int_next_lifetime)
    {
        return true;
    }

    /**
     *
     * @param string $encryption_key
     * @param string $cookie_domain
     * @param string $session_id
     * @return CookieSessionHandler
     */
    public static function getInstance($encryption_key = null, $cookie_domain = '', $session_id = 'omfg')
    {
        if(is_null($encryption_key))
        {
            $encryption_key = defined('COOKIE_ENCRYPTION_KEY') ? COOKIE_ENCRYPTION_KEY : 'abjh23cpi7cz';
        }
        if(is_null(self::$obj_session))
        {
            self::$obj_session = new CookieSessionHandler($encryption_key, $cookie_domain, $session_id);
            if(isset($_SESSION['sess_exp']))
            {
                self::$obj_session->setExpiry($_SESSION['sess_exp']);
            }
        }
        return self::$obj_session;
    }

    /**
     *
     * @param string $in
     * @param string $salt
     * @return string
     */
    public static function cookieCrypt($in, $salt)
    {
        if(empty($in))
        {
            return '';
        }
        $td = mcrypt_module_open('tripledes', '', 'ecb', '');
        $iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
        mcrypt_generic_init($td, $salt, $iv);
        $cypher = base64_encode(mcrypt_generic($td, $in));
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        if(strlen($cypher) > 4000)
        {
            throw new Exception('cookie data overflow');
        }
        return $cypher;
    }

    /**
     *
     * @param string $in
     * @param string $salt
     * @return string
     */
    public static function cookieDecrypt($in, $salt)
    {
        if(empty($in))
        {
            return '';
        }
        $td = mcrypt_module_open('tripledes', '', 'ecb', '');
        $iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
        mcrypt_generic_init($td, $salt, $iv);
        $plain_text = rtrim(mdecrypt_generic($td, base64_decode($in)), "\0");
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        return $plain_text;
    }
}

interface CacheInterface
{
    /**
     *
     * @param string $id
     * @param mixed $value
     * @param integer $ttl
     */
    public function store($id, $value, $ttl);
    /**
     *
     * @param string $id
     * @return mixed
     */
    public function fetch($id);
    /**
     *
     * @param string $id
     */
    public function delete($id);
}

class APCCache implements CacheInterface
{
    private static $instance;

    /**
     *
     * @return APCCache
     */
    public static function getInstance()
    {
        if(is_null(self::$instance))
        {
            self::$instance = new self;
        }
        return self::$instance;
    }

    private function __construct()
    {
        if(!function_exists('apc_store'))
        {
            throw new Exception('APC must be installed to use this backend');
        }
    }

    /**
     * Store value
     *
     * @param string $id Value identifier
     * @param mixed $value Value to be stored
     * @param integer $ttl Cache time to live
     * @return boolean
     */
    public function store($id, $value, $ttl = 0)
    {
        return apc_store($id, $value, $ttl);
    }

    /**
     * Add value. Same as store, but will not overwrite an existing value.
     *
     * @param string $id Value identifier
     * @param mixed $value Value to be stored
     * @param integer $ttl Cache time to live
     * @return boolean
     */
    public function add($id, $value, $ttl = 0)
    {
        if(($val = $this->fetch($id)) === false)
        {
            return $this->store($id, $value, $ttl);
        }
        return false;
    }

    /**
     * Fetch value
     *
     * @param string $id Value identifier
     * @return mixed Returns value or false
     */
    public function fetch($id)
    {
        return apc_fetch($id);
    }

    /**
     * Delete value from cache
     *
     * @param string $id Value identifier
     * @return boolean
     */
    public function delete($id)
    {
        return apc_delete($id);
    }
}

class FileCache implements CacheInterface
{
    private $path;
    /**
     * New file cache
     *
     * @param Array Options are cache path ('path') and wether to create it ('create')
     */
    public function __construct(array $options = null)
    {
        if(!is_array($options))
        {
            $options = array();
        }
        if(!isset($options['path']))
        {
            $options['path'] = sys_get_temp_dir();
        }
        if(!isset($options['create']))
        {
            $options['create'] = false;
        }

        $slash = substr($options['path'], -1);
        if($slash != '/' and $slash !='\\')
        {
            $options['path'] .= '/';
        }

        if($options['create'])
        {
            if(!is_dir($options['path']))
            {
                if(!mkdir($options['path'], 0755, true))
                {
                    throw new \shozu\Cache\Exception('directory "' . $options['path'] . '" does ot exist and could not be created.');
                }
            }
        }

        $this->path = $options['path'];
    }

    /**
     * Store value
     *
     * @param string $id Value identifier
     * @param mixed $value Value to be stored
     * @param integer $ttl Cache time to live
     * @return boolean
     */
    public function store($id, $value, $ttl = 0)
    {
        $file = $this->fileName($id);
        if($ttl == 0)
        {
            $expires = 0;
        }
        else
        {
            $expires = time() + (int)$ttl;
        }
        if(file_put_contents($file,$expires
            . "\n" . serialize($value)))
        {
            return true;
        }
    }

    /**
     * Add value. Same as store, only will not overwrite existing value
     *
     * @param string $id Value identifier
     * @param mixed $value Value to be stored
     * @param integer $ttl Cache time to live
     * @return boolean
     */
    public function add($id, $value, $ttl = 0)
    {
        if(($val = $this->fetch($id)) === false)
        {
            return $this->store($id, $value, $ttl);
        }
        return false;
    }

    /**
     * Fetch value
     *
     * @param string $id Value identifier
     * @return mixed Returns value or false
     */
    public function fetch($id)
    {
        $fileName = $this->fileName($id);
        $old = ini_set('error_reporting', 0);
        if(($file = fopen($fileName, 'r')) === false)
        {
            ini_set('error_reporting', $old);
            return false;
        }
        ini_set('error_reporting', $old);
        $expires = (int)fgets($file);
        if($expires > time() or $expires === 0)
        {
            $data = '';
            while(($line = fgets($file)) !== false)
            {
                $data .= $line;
            }
            fclose($file);
            return unserialize($data);
        }
        fclose($file);
        unlink($fileName);
        return false;
    }

    /**
     * Delete value from cache
     *
     * @param string $id Value identifier
     * @return boolean
     */
    public function delete($id)
    {
        $file = $this->fileName($id);
        if(is_file($file))
        {
            return unlink($file);
        }
        return false;
    }

    /**
     * Remove no more valid cache entries
     *
     * @return integer the number of entries removed
     */
    public function clean()
    {
        $erased = 0;
        $files = glob($this->path . '*.cache');
        foreach($files as $file)
        {
            if(($handle = $this->fileHandle($file)) !== false)
            {
                $expires = (int)fgets($handle);
                if($expires < time())
                {
                    fclose($handle);
                    unlink($file);
                    $erased++;
                }
            }
        }
        return $erased;
    }

    private function fileName($id)
    {
        return $this->path . md5($id) . '.cache';
    }

    private function fileHandle($fileName)
    {
        $old = ini_set('error_reporting', 0);
        if(($file = fopen($fileName, 'r')) === false)
        {
            ini_set('error_reporting', $old);
            return false;
        }
        ini_set('error_reporting', $old);
        return $file;
    }
}

class Template
{
    /**
     *  String of template file
     */
    private $file;
    /**
     * Array of template variables
     */
    private $vars = array();

    private $cache_id;

    private $cache;

    /**
     * Assign the template path
     *
     * @param string $file Template path (absolute path or path relative to the templates dir)
     * @param array $vars assigned variables
     */
    public function __construct($file, $vars = false)
    {
        $this->file = $file;
        if (!file_exists($this->file))
        {
            throw new Exception("View '{$this->file}' not found!");
        }
        if ($vars !== false)
        {
            $this->vars = $vars;
        }
    }

    /**
     * Assign specific variable to the template
     *
     * <code>
     * // assign single var
     * $view->assign('varname', 'varvalue');
     * // assign array of vars
     * $view->assign(array('varname1' => 'varvalue1', 'varname2' => 'varvalue2'));
     * </code>
     *
     * @param mixed $name Variable name
     * @param mixed $value Variable value
     */
    public function assign($name, $value = null)
    {
        if (is_array($name))
        {
            array_merge($this->vars, $name);
        }
        else
        {
            $this->vars[$name] = $value;
        }
    }


    /**
     * Return template output as string
     *
     * @return string content of compiled view template
     */
    public function render()
    {
        ob_start();
        extract($this->vars, EXTR_SKIP);
        include $this->file;
        $content = ob_get_clean();
        return $content;
    }

    /**
     * Render the content and return it
     *
     * <code>
     * echo new View('blog', array('title' => 'My title'));
     * </code>
     *
     * @return string content of the view
     */
    public function __toString()
    {
        return $this->render();
    }

    /**
     * Escape HTML special chars
     *
     * @param string
     * @return string
     */
    public function escape($string)
    {
        return htmlspecialchars($string);
    }

    /**
     * Limit string to given length but do not truncate words
     *
     * @param string $str input string
     * @param integer $length length limit
     * @param integer $minword
     * @return string
     */
    public function limit($str, $length, $minword = 3)
    {
        $sub = '';
        $len = 0;
        foreach (explode(' ', $str) as $word)
        {
            $part = (($sub != '') ? ' ' : '') . $word;
            $sub .= $part;
            $len += strlen($part);
            if (strlen($word) > $minword && strlen($sub) >= $length)
            {
                break;
            }
        }
        return $sub . (($len < strlen($str)) ? '...' : '');
    }

    /**
     * Multibyte-aware ucfirst.
     *
     * Uppercase first letter
     *
     * @param string $str
     * @param string $e encoding, defaults to utf-8
     * @return string
     */
    public function ucfirst($str, $e = 'utf-8')
    {
        $fc = mb_strtoupper(mb_substr($str, 0, 1, $e), $e);
        return $fc . mb_substr($str, 1, mb_strlen($str, $e), $e);
    }

    /**
     * Cache portions of a view. Usage:
     *
     * <code>
     * <?php if($this->cacheBegin('myCacheId')){ ?>
     * <!-- some dynamic content here will be cached for 600 seconds -->
     * <?php $this->cacheEnd(600);} ?>
     * </code>
     *
     * @param string $id
     * @return boolean
     */
    public function cacheBegin($id)
    {
        if(is_null($this->cache))
        {
            return false;
        }
        $cache = $this->cache;
        $this->cache_id = $id;
        if(($contentFromCache = $cache->fetch($id)) === false)
        {
            ob_start();
            return true;
        }
        else
        {
            echo $contentFromCache;
            return false;
        }
    }

    /**
     *
     * @param integer $ttl
     */
    public function cacheEnd($ttl = 0)
    {
        if(is_null($this->cache))
        {
            return;
        }
        $cache = $this->cache;
        if(($contentFromCache = $cache->fetch($this->cache_id)) === false)
        {
            $contentToCache = ob_get_contents();
            $cache->store($this->cache_id, $contentToCache, $ttl);
            ob_end_clean();
            echo $contentToCache;
        }
        else
        {
            ob_end_clean();
        }
    }

    public function setCache(CacheInterface $cache)
    {
        $this->cache = $cache;
    }
}

class Benchmark
{
    public static $marks = array();
    public static $enabled = false;
	/**
	 * Benchmark start point
	 *
	 * @param string $name point name
	 * @return boolean
	 */
    public static function start($name)
    {
        if (!self::$enabled)
        {
            return false;
        }
        if (!isset(self::$marks[$name]))
        {
            self::$marks[$name] = array(
                'start' => microtime(true) ,
                'stop' => false,
                'memory_start' => function_exists('memory_get_usage') ? memory_get_usage() : 0,
                'memory_stop' => false
            );
        }
        return true;
    }

	/**
	 * Benchmark stop point
	 *
	 * @param string $name point name
	 * @return boolean
	 */
    public static function stop($name)
    {
        if (!self::$enabled)
        {
            return false;
        }
        if (isset(self::$marks[$name]))
        {
            self::$marks[$name]['stop'] = microtime(true);
            self::$marks[$name]['memory_stop'] = function_exists('memory_get_usage') ? memory_get_usage() : 0;
        }
        return true;
    }
    /**
	 * Get the elapsed time between a start and stop of a mark name, TRUE for all.
	 *
	 * @param string $name
	 * @param integer $decimals
	 * @return array
	 */
    public static function get($name, $decimals = 4)
    {
        if (!self::$enabled)
        {
            return false;
        }
        if ($name === true)
        {
            $times = array();
            foreach(array_keys(self::$marks) as $name)
            {
                $times[$name] = self::get($name, $decimals);
            }
            return $times;
        }
        if (!isset(self::$marks[$name]))
        {
            return false;
        }
        if (self::$marks[$name]['stop'] === false)
        {
            self::stop($name);
        }
        return array(
            'time' => number_format(self::$marks[$name]['stop'] - self::$marks[$name]['start'], $decimals) ,
            'memory' => self::convert_size(self::$marks[$name]['memory_stop'] - self::$marks[$name]['memory_start'])
        );
    }

	/**
	 * Convert byte size in human readable format
	 *
	 * @param integer
	 * @return string
	 */
    public static function convert_size($num)
    {
        if ($num >= 1073741824)
        {
            $num = round($num / 1073741824 * 100) / 100 . ' gb';
        }
        else if ($num >= 1048576)
        {
            $num = round($num / 1048576 * 100) / 100 . ' mb';
        }
        else if ($num >= 1024)
        {
            $num = round($num / 1024 * 100) / 100 . ' kb';
        }
        else
        {
            $num.= ' b';
        }
        return $num;
    }

	/**
	 * Generate HTML-formatted report
	 *
	 * @return string
	 */
    public static function htmlReport()
    {
        if (!self::$enabled)
        {
            return '';
        }
        $html = '<div  style="font-size:14px;font-family:monospace;"><ol>';
        foreach(self::get(true) as $key => $val)
        {
            $html.= '<li><strong>' . htmlspecialchars($key) . '</strong><br/>time&nbsp;&nbsp;: ' . $val['time'] . '<br/>memory: ' . $val['memory'] . '</li>';
        }
        return $html . '</ol></div>';
    }

	/**
	 * Generate CLI/text formatted report
	 *
	 * @return string
	 */
    public static function cliReport()
    {
        $output = '';
        if (!self::$enabled)
        {
            return $output;
        }
        $points = self::get(true);
        if (!empty($points))
        {
            $output.= "\n#### Benchmark ####\n";
            foreach($points as $key => $val)
            {
                $output.= "\n[ " . $key . " ]\n        time: " . $val['time'] . "\n        memory: " . $val['memory'] . "\n";
            }
        }
        return $output;
    }

	/**
	 * Enable benchmark
	 *
	 * @return boolean state
	 */
    public static function enable()
    {
        self::$enabled = true;
        return self::$enabled;
    }

	/**
	 * Disable benchmark
	 *
	 * @return boolean state
	 */
    public static function disable()
    {
        self::$enabled = false;
        return self::$enabled;
    }
}

class MockRequest implements RequestInterface
{
    private $date_time;
    private $cookie = array();
    private $post = array();
    private $get = array();
    private $server = array();
    private $session = array();
    private $params = array();
    private $body = '';
    private $files = array();
    private $request_method = 'GET';
    private $requested_url = '/';

    public function  __construct()
    {
        $this->date_time = \DateTime::createFromFormat('Y-m-d H:i:s', time('Y-m-d H:i:s'));
    }

    /**
     *
     * @return string
     */
    public function getRequestedUrl()
    {
        return $this->requested_url;
    }

    /**
     * @return \DateTime
     */
    public function getDateTime()
    {
        return $this->date_time;
    }


    /**
     *
     * @return string
     */
    public function getRequestMethod()
    {
        return $this->request_method;
    }

    /**
     *
     * @param string $http_verb
     * @return MockRequest
     */
    public function setRequestMethod($http_verb)
    {
        $this->request_method = $http_verb;
        return $this;
    }

    /**
     * @return string
     */
    public function getCookieVar($cookie_name)
    {
        if(isset($this->cookie[$cookie_name]))
        {
            return $this->cookie[$cookie_name];
        }
    }

    /**
     *
     * @param string $cookie_name
     * @param string $cookie_value
     * @return MockRequest
     */
    public function setCookieVar($cookie_name, $cookie_value)
    {
        $this->cookie[$cookie_name] = $cookie_value;
        return $this;
    }

    /**
     * @return string
     */
    public function getPostVar($post_var_name)
    {
        if(isset($this->post[$post_var_name]))
        {
            return $this->post[$post_var_name];
        }
    }

    /**
     *
     * @param string $post_var_name
     * @param string $post_var_value
     * @return MockRequest
     */
    public function setPostVar($post_var_name, $post_var_value)
    {
        $this->post[$post_var_name] = $post_var_value;
        return $this;
    }

    /**
     * @return string
     */
    public function getGetVar($get_var_name)
    {
        if(isset($this->get[$get_var_name]))
        {
            return $this->get[$get_var_name];
        }
    }

    /**
     *
     * @param string $get_var_name
     * @param string $get_var_value
     * @return MockRequest 
     */
    public function setGetVar($get_var_name, $get_var_value)
    {
        $this->get[$get_var_name] = $get_var_value;
        return $this;
    }

    /**
     * @return string
     */
    public function getServerVar($server_var_name)
    {
        if(isset($this->server[$server_var_name]))
        {
            return $this->server[$server_var_name];
        }
    }

    /**
     *
     * @param string $server_var_name
     * @param string $server_var_value
     * @return MockRequest 
     */
    public function setServerVar($server_var_name, $server_var_value)
    {
        $this->server[$server_var_name] = $server_var_value;
        return $this;
    }

    /**
     * @return string
     */
    public function getSessionVar($session_var_name)
    {
        if(isset($this->session[$session_var_name]))
        {
            return $this->session[$session_var_name];
        }
    }

    /**
     *
     * @param string $session_var_name
     * @param string $session_var_value
     * @return MockRequest
     */
    public function setSessionVar($session_var_name, $session_var_value)
    {
        $this->session[$session_var_name] = $session_var_value;
        return $this;
    }

    /**
     *
     * @todo  implement method
     * @param string $uploaded_file_name
     * @return Array
     */
    public function getUploadedFile($uploaded_file_name)
    {
        if(isset($this->files[$uploaded_file_name]))
        {
            return $this->files[$uploaded_file_name];
        }
    }

    /**
     *
     * @param string $upload_name
     * @param string $type
     * @param string $tmp_name
     * @param integer $size
     * @param integer $error
     * @return MockRequest
     */
    public function setUploadedFile($upload_name, $type, $tmp_name, $size, $error = \UPLOAD_ERR_OK)
    {
        $this->files[$upload_name] = array(
            'name' => $upload_name,
            'type' => $type,
            'tmp_name' => $tmp_name,
            'error' => $error,
            'size' => (int)$size
        );
        return $this;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     *
     * @param string $body_content
     * @return MockRequest 
     */
    public function setBody($body_content)
    {
        $this->body = $body_content;
        return $this;
    }

    /**
     *
     * @param string $param_name
     * @param string $param_value
     * @return MockRequest
     */
    public function setParam($param_name, $param_value)
    {
        $this->params[$param_name] = $param_value;
        return $this;
    }

    /**
     *
     * @param string $param_name
     */
    public function getParam($param_name)
    {
        if(isset($this->params[$param_name]))
        {
            return $this->params[$param_name];
        }
    }
}




if(!count(debug_backtrace()))
{
    class myAction extends ActionAbstract
    {
        public function getResponse()
        {
            $response = new HTMLResponse;
            $response->setBody('hello ' . $this->getRequest()->getPostVar('toto'));
            return $response;
        }
    }

    try
    {
        $request = new MockRequest;
        $request
            ->setParam('pouet', 123)
            ->setPostVar('toto', 'ragnagna');

        $action = new myAction($request);
        $response = $action->getResponse();
        if(!($response instanceof HTMLResponse))
        {
            throw new Exception('wrong response');
        }
        if($response->getBody() != 'hello ragnagna')
        {
            throw new Exception('wrong response');
        }
        echo $response->getBody();
    }
    catch(\Exception $e)
    {
        echo get_class($e) . ': ' . $e->getMessage() . "\n\n" . $e->getTraceAsString() . "\n";
    }
}