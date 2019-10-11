<?php

namespace jinyicheng\thinkphp_status;

class Status
{
    /**
     * @var array 配置参数
     */
    private static $status = [];

    /**
     * @var string 参数作用域
     */
    private static $range = '_sys_';

    /**
     * 设定配置参数的作用域
     * @access public
     * @param  string $range 作用域
     * @return void
     */
    public static function range($range)
    {
        self::$range = $range;

        if (!isset(self::$status[$range])) self::$status[$range] = [];
    }

    /**
     * 解析配置文件或内容
     * @access public
     * @param  string $status 配置文件路径或内容
     * @param  string $type   配置解析类型
     * @param  string $name   配置名（如设置即表示二级配置）
     * @param  string $range  作用域
     * @return mixed
     */
    public static function parse($status, $type = '', $name = '', $range = '')
    {
        $range = $range ?: self::$range;

        if (empty($type)) $type = pathinfo($status, PATHINFO_EXTENSION);

        $class = false !== strpos($type, '\\') ?
            $type :
            '\\think\\config\\driver\\' . ucwords($type);

        return self::set((new $class())->parse($status), $name, $range);
    }

    /**
     * 加载配置文件（PHP格式）
     * @access public
     * @param  string $file  配置文件名
     * @param  string $name  配置名（如设置即表示二级配置）
     * @param  string $range 作用域
     * @return mixed
     */
    public static function load($file, $name = '', $range = '')
    {
        $range = $range ?: self::$range;

        if (!isset(self::$status[$range])) self::$status[$range] = [];

        if (is_file($file)) {
            $name = strtolower($name);
            $type = pathinfo($file, PATHINFO_EXTENSION);

            if ('php' == $type) {
                return self::set(include $file, $name, $range);
            }

            if ('yaml' == $type && function_exists('yaml_parse_file')) {
                return self::set(yaml_parse_file($file), $name, $range);
            }

            return self::parse($file, $type, $name, $range);
        }

        return self::$status[$range];
    }

    /**
     * 检测配置是否存在
     * @access public
     * @param  string $name 配置参数名（支持二级配置 . 号分割）
     * @param  string $range  作用域
     * @return bool
     */
    public static function has($name, $range = '')
    {
        $range = $range ?: self::$range;

        if (!strpos($name, '.')) {
            return isset(self::$status[$range][strtolower($name)]);
        }

        // 二维数组设置和获取支持
        $name = explode('.', $name, 2);
        return isset(self::$status[$range][strtolower($name[0])][$name[1]]);
    }

    /**
     * 获取配置参数 为空则获取所有配置
     * @access public
     * @param  string $name 配置参数名（支持二级配置 . 号分割）
     * @param  string $range  作用域
     * @return mixed
     */
    public static function get($name = null, $range = '')
    {
        $range = $range ?: self::$range;

        // 无参数时获取所有
        if (empty($name) && isset(self::$status[$range])) {
            return self::$status[$range];
        }

        // 非二级配置时直接返回
        if (!strpos($name, '.')) {
            $name = strtolower($name);
            return isset(self::$status[$range][$name]) ? self::$status[$range][$name] : null;
        }

        // 二维数组设置和获取支持
        $name    = explode('.', $name, 2);
        $name[0] = strtolower($name[0]);

        if (!isset(self::$status[$range][$name[0]])) {
            // 动态载入额外配置
            $module = Request::instance()->module();
            $file   = CONF_PATH . ($module ? $module . DS : '') . 'status' . DS . $name[0] . CONF_EXT;

            is_file($file) && self::load($file, $name[0]);
        }

        return isset(self::$status[$range][$name[0]][$name[1]]) ?
            self::$status[$range][$name[0]][$name[1]] :
            null;
    }

    /**
     * 设置配置参数 name 为数组则为批量设置
     * @access public
     * @param  string|array $name  配置参数名（支持二级配置 . 号分割）
     * @param  mixed        $value 配置值
     * @param  string       $range 作用域
     * @return mixed
     */
    public static function set($name, $value = null, $range = '')
    {
        $range = $range ?: self::$range;

        if (!isset(self::$status[$range])) self::$status[$range] = [];

        // 字符串则表示单个配置设置
        if (is_string($name)) {
            if (!strpos($name, '.')) {
                self::$status[$range][strtolower($name)] = $value;
            } else {
                // 二维数组
                $name = explode('.', $name, 2);
                self::$status[$range][strtolower($name[0])][$name[1]] = $value;
            }

            return $value;
        }

        // 数组则表示批量设置
        if (is_array($name)) {
            if (!empty($value)) {
                self::$status[$range][$value] = isset(self::$status[$range][$value]) ?
                    array_merge(self::$status[$range][$value], $name) :
                    $name;

                return self::$status[$range][$value];
            }

            return self::$status[$range] = array_merge(
                self::$status[$range], array_change_key_case($name)
            );
        }

        // 为空直接返回已有配置
        return self::$status[$range];
    }

    /**
     * 重置配置参数
     * @access public
     * @param  string $range 作用域
     * @return void
     */
    public static function reset($range = '')
    {
        $range = $range ?: self::$range;

        if (true === $range) {
            self::$status = [];
        } else {
            self::$status[$range] = [];
        }
    }
}
