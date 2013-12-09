<?php

class Conf
{
    private static $instance = null;

    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new Conf();
        }
        return self::$instance;
    }
}