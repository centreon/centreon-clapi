<?php
/*
 * Copyright 2005-2009 MERETHIS
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
 
class CentreonServiceGroup {
	private $DB;
	
	public function __construct($DB) {
		$this->DB = $DB;
	}

	/*
	 * Check host existance
	 */
	public function serviceGroupExists($name) {
		if (!isset($name))
			return 0;
		
		/*
		 * Get informations
		 */
		$DBRESULT =& $this->DB->query("SELECT sg_name, sg_id FROM servicegroup WHERE sg_name = '".htmlentities($name, ENT_QUOTES)."'");
		if ($DBRESULT->numRows() >= 1) {
			$sg =& $DBRESULT->fetchRow();
			$DBRESULT->free();
			return $sg["sg_id"];
		} else {
			return 0;
		}
	}
	
	public function delServiceGroup($name) {
		$request = "DELETE FROM servicegroup WHERE sg_name LIKE '$name'";
		$DBRESULT =& $this->DB->query($request);
		$this->return_code = 0;
		return;
	}
	
	public function listServiceGroup($search = NULL) {
		$searchStr = "";
		if (isset($search) && $search != "") {
			$searchStr = " WHERE sg_name LILE '%".htmlentities($search, ENT_QUOTES)."%'";
		}
		$request = "SELECT sg_name, sg_alias FROM servicegroup $searchStr ORDER BY sg_name";
		$DBRESULT =& $this->DB->query($request);
		while ($data =& $DBRESULT->fetchRow()) {
			print $data["sg_name"].";".$data["sg_alias"]."\n";
		}
		$DBRESULT->free();
		
	}
	
	public function addServiceGroup($information) {
		if (!isset($information["sg_name"])) {
			return 0;
		} else {
			if (!isset($information["sg_alias"]) || $information["sg_alias"] == "")
				$information["sg_alias"] = $information["sg_name"];
			
			$request = "INSERT INTO servicegroup (sg_name, sg_alias, sg_activate) VALUES ('".htmlentities($information["sg_name"], ENT_QUOTES)."', '".htmlentities($information["sg_alias"], ENT_QUOTES)."', '1')";
			$DBRESULT =& $this->DB->query($request);
	
			$sg_id = $this->getServiceGroupID($information["sg_name"]);
			return $sg_id;
		}
	}
	
	public function getServiceGroupID($sg_name = NULL) {
		if (!isset($sg_name))
			return;
			
		$request = "SELECT sg_id FROM servicegroup WHERE sg_name LIKE '$sg_name'";
		$DBRESULT =& $this->DB->query($request);
		$data =& $DBRESULT->fetchRow();
		return $data["sg_id"];
	}
}
?>