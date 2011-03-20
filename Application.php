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
     * @return array
     */
    public static function getTranslations($lang_id);
}