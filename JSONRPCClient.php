<?php
namespace shozu;
class JSONRPCException extends \Exception
{
    private $response;
    private $request;
    public function setResponse($response)
    {
        $this->response = $response;
    }
    public function getResponse()
    {
        return $this->response;
    }
    public function setRequest($response)
    {
        $this->request = $response;
    }
    public function getRequest()
    {
        return $this->request;
    }
}
class JSONRPCClient
{
    private $lastRequest;
    private $lastResponse;
    private $endpoint;
    private $auth_string;
    public function __construct($endpoint, $user = null, $password = null)
    {
        $this->endpoint = (string)$endpoint;
        if(!is_null($user) && !is_null($password))
        {
            $this->auth_string = base64encode($user . ':' . $password);
        }
    }

    /**
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $id = uniqid();
        $request = array(
            'method' => $name,
            'params' => $arguments,
            'id' => $id);
        $content = json_encode($request);
        $this->lastRequest = $content;
        $options = array('http' => array(
                'method' => 'POST',
                'header' => !is_null($this->auth_string) ? "Authorization: Basic {$this->auth_string} \r\nContent-type: application/json" : 'Content-type: application/json',
                'content' => $content
        ));
        $context  = stream_context_create($options);
        if(($fp = fopen($this->endpoint, 'r', false, $context)) === false)
        {
            $e = new JSONRPCException('could not get response from server');
            $e->setRequest($request);
            throw $e;
        }
        $raw_response = '';
        while($row = fgets($fp))
        {
            $raw_response .= trim($row)."\n";
        }
        $this->lastResponse = $raw_response;
        $response = json_decode($raw_response,true);
        if(is_null($response))
        {
            $e = new JSONRPCException('could not decode response');
            $e->setResponse($raw_response);
            $e->setRequest($request);
            throw $e;
        }
        if($response['id'] != $id)
        {
            $e = new JSONRPCException('wrong id');
            $e->setResponse($response);
            $e->setRequest($request);
            throw $e;
        }
        if(!is_null($response['error']))
        {
            $e = new JSONRPCException($response['error']['message']);
            $e->setResponse($response);
            $e->setRequest($request);
            throw $e;
        }
        return $response['result'];
    }

    /**
     *
     * @return string
     */
    public function getLastRequest()
    {
        return $this->lastRequest;
    }

    /**
     *
     * @return string
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }
}


if(!count(debug_backtrace()))
{
    $client = new JSONRPCClient('http://www.desfrenes.com/services/json-rpc.php');
    try
    {
        echo $client->sayHello('john doe') . "\n";
        echo $client->getTime() . "\n";
    }
    catch(JSONRPCException $e)
    {
        echo get_class($e) . ': ' . $e->getMessage() . "\n";
        echo $client->getLastRequest() . "\n" . $client->getLastResponse() . "\n";
    }
}