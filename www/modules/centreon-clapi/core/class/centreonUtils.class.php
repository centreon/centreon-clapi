<?php

class CentreonUtils
{
    /**
     * @var string
     */
    private static $centreonPath;

    /**
     * Get centreon application path
     *
     * @return string
     */
    public static function getCentreonPath()
    {
        if (isset(self::$centreonPath)) {
            return self::$centreonPath;
        }
        $db = Centreon_Db_Manager::factory('centreon');
        $res = $db->query("SELECT `value` FROM options WHERE `key` = 'oreon_path'");
        $row = $res->fetch();
        self::$centreonPath = $row['value'];
        return self::$centreonPath = $row['value'];
    }

    /**
     * Converts strings such as #S# #BS# #BR#
     *
     * @param string $pattern
     * @return string
     */
    public function convertSpecialPattern($pattern)
    {
        $pattern = str_replace("#S#", "/", $pattern);
        $pattern = str_replace("#BS#", "\\", $pattern);
        $pattern = str_replace("#BR#", "\n", $pattern);
        return $pattern;
    }
}