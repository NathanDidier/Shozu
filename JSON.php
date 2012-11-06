<?php
namespace shozu;
/**
 * Encapsulating php's json_* to get proper Exception support
 */
class JSON
{
    /**
     * @param $value
     * @param int $options
     * @return string
     * @throws JSON\EncodeException
     */
    public static function encode($value, $options = 0)
    {
        $json = json_encode($value, $options);
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new JSON\EncodeException('JSON encoding error', json_last_error());
        }

        return $json;
    }

    /**
     * @param $json
     * @param bool $assoc
     * @param int $depth
     * @param int $options
     * @return mixed|null
     * @throws JSON\DecodeException
     */
    public static function decode($json, $assoc = false, $depth = 512, $options = 0)
    {
        $data = json_decode($json, $assoc = false, $depth = 512, $options = 0);
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new JSON\DecodeException('JSON decoding error', json_last_error());
        }

        return $data;
    }
}
