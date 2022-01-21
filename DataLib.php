<?php

/**
 * @name DataLibLoad
 * @author Neo-Developer
 * @main Neo\DataLibLoad
 * @version 0.1.0
 * @api 4.0.6
 */

namespace Neo;

use pocketmine\plugin\PluginBase;

class DataLibLoad extends PluginBase{}

class DataLib {
    private static array $data = [];

    public static function setData(array $value) : void {
        self::$data = $value;
    }

    public static function getData() : array {
        return self::$data;
    }


}