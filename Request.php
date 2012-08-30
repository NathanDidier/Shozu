<?php
namespace shozu;
class Request
{
    public $acceptable_content_types = null;

    public function getFormat()
    {
        $keys = array_keys($this->getAcceptableContentTypes());

        return array_shift($keys);
    }

    public function getAcceptableContentTypes()
    {
        if (is_null($this->acceptable_content_types)) {
            if(! isset($_SERVER['HTTP_ACCEPT']) ||
                strlen($_SERVER['HTTP_ACCEPT']) == 0)
            {
                return array();
            }

            $accepts = array();
            foreach (preg_split('/\s*,\s*/', $_SERVER['HTTP_ACCEPT']) as $a) {
                if (($parts = preg_split('/;\s*q=/', $a))) {
                    $a = $parts[0];
                    $q = isset($parts[1]) ? (float) $parts[1] : 1;
                }

                $accepts[$a] = $q;
            }
            arsort($accepts);

            $this->acceptable_content_types = $accepts;
        }

        return $this->acceptable_content_types;
    }
}
