<?php
/*
 * Copyright 2005-2010 MERETHIS
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give MERETHIS
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of MERETHIS choice, provided that
 * MERETHIS also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 * SVN : $URL: http://svn.modules.centreon.com/centreon-clapi/trunk/www/modules/centreon-clapi/core/class/centreonHost.class.php $
 * SVN : $Id: centreonHost.class.php 25 2010-03-30 05:52:19Z jmathis $
 *
 */

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

    /**
     * show list of timeperiods
     *
     * @param string $search
     * @return int
     */
    public function show($search = null)
    {
        $searchQuery = "";
        if (isset ($search) && $search) {
            $searchQuery = " WHERE tp_name LIKE '%".htmlentities($search, ENT_QUOTES)."%'
            				 OR tp_alias LIKE '%".htmlentities($search, ENT_QUOTES)."%' ";
        }
        $query = "SELECT * FROM timeperiod $searchQuery ORDER BY tp_name";
        $res = $this->_db->query($query);
        $i = 0;
        while ($row = $res->fetchRow()) {
            if (!$i) {
                print "name;alias;sunday;monday;tuesday;wednesday;thursday;friday,saturday\n";
            }
            print html_entity_decode($row['tp_name'].";".$row['tp_alias'].";".$row['tp_sunday'].";".$row['tp_monday'].";".$row['tp_tuesday'].";".$row['tp_wednesday'].";".$row['tp_thursday'].";".$row['tp_friday'].";".$row['tp_saturday']."\n", ENT_QUOTES);
            $i++;
        }
        return 0;
    }
}