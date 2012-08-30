<?php
namespace shozu;
interface Application
{
    /**
     * @return array
     */
    public static function getRoutes();

    /**
     * @return array
     */
    public static function getObservers();

    /**
     * @param $lang_id
     * @return array
     */
    public static function getTranslations($lang_id);
}
