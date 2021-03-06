<?php
namespace shozu;
/**
 * Wraps PHP session with an OO API
 *
 * <code>
 * $session = Session::getInstance();
 * $session->myVar = 'my value';
 * echo $session->myVar;
 * </code>
 */
class Session
{
    private static $instance;

    /**
     * Get Session instance
     *
     * @param  string  $name session name (defaults to PHPSESSID)
     * @return Session
     */
    public static function getInstance($name = 'PHPSESSID')
    {
        if (empty(self::$instance)) {
            self::startSession($name);
            self::$instance = new Session;
        }

        return self::$instance;
    }

    public function __get($key)
    {
        if (isset($_SESSION[$key])) {
            return $_SESSION[$key];
        }
    }

    public function __set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public function __isset($key)
    {
        return isset($_SESSION[$key]);
    }

    private static function startSession($name)
    {
        if (!isset($_SESSION)) {
            if (headers_sent()) {
                throw new \Exception('headers already sent by');
            }
            session_name($name);
            session_start();
        }
    }
}
