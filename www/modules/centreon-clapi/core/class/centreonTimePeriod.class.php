<?php

class CentreonTimePeriod
{
    /**
     *
     * @var CentreonDB
     */
    protected $_db;

    /**
     * constructor
     *
     * @param CentreonDB $db
     * @return void
     */
    public function __construct($db)
    {
        $this->_db = $db;
    }

    /**
     * Returns true if timeperiod exists
     *
     * @param string $name
     * @return boolean
     */
    public function timeperiodExists($name)
    {
        $query = "SELECT tp_name FROM timeperiod WHERE tp_name = '".htmlentities($name, ENT_QUOTES)."'";
        $res = $this->_db->query($query);
        if ($res->numRows()) {
            return true;
        }
        return false;
    }

	/**
	 * Gets id of timeperiod
	 * returns 0 if not found
	 *
     * @param string $name
     * @return int
     */
    public function getTimeperiodId($name)
    {
        $query = "SELECT tp_id FROM timeperiod WHERE tp_name = '".htmlentities($name, ENT_QUOTES)."'";
        $res = $this->_db->query($query);
        while ($row = $res->fetchRow()) {
            return $row['tp_id'];
        }
        return 0;
    }
}