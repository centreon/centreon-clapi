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
 
class CentreonHostGroup {
	private $DB;
	
	public function __construct($DB) {
		$this->DB = $DB;
	}

	/*
	 * Check host existance
	 */
	public function hostGroupExists($name) {
		if (!isset($name))
			return 0;
		
		/*
		 * Get informations
		 */
		$DBRESULT =& $this->DB->query("SELECT hg_name, hg_id FROM hostgroup WHERE hg_name = '".htmlentities($name, ENT_QUOTES)."'");
		if ($DBRESULT->numRows() >= 1) {
			$host =& $DBRESULT->fetchRow();
			$DBRESULT->free();
			return $host["hg_id"];
		} else {
			return 0;
		}
	}
	
	public function delHostGroup($name) {
		$request = "DELETE FROM hostgroup WHERE hg_name LIKE '$name'";
		$DBRESULT =& $this->DB->query($request);
		$this->return_code = 0;
		return;
	}
	
	public function listHostGroup($search = NULL) {
		$searchStr = "";
		if (isset($search) && $search != "") {
			$searchStr = " WHERE hg_name LILE '%".htmlentities($search, ENT_QUOTES)."%'";
		}
		$request = "SELECT hg_name, hg_alias FROM hostgroup $searchStr ORDER BY hg_name";
		$DBRESULT =& $this->DB->query($request);
		while ($data =& $DBRESULT->fetchRow()) {
			print $data["hg_name"].";".$data["hg_alias"]."\n";
		}
		$DBRESULT->free();
		
	}
	
	public function addHostGroup($information) {
		if (!isset($information["hg_name"])) {
			return 0;
		} else {
			if (!isset($information["hg_alias"]) || $information["hg_alias"] == "")
				$information["hg_alias"] = $information["hg_name"];
			
			$request = "INSERT INTO hostgroup (hg_name, hg_alias, hg_activate) VALUES ('".htmlentities($information["hg_name"], ENT_QUOTES)."', '".htmlentities($information["hg_alias"], ENT_QUOTES)."', '1')";
			$DBRESULT =& $this->DB->query($request);
	
			$hg_id = $this->getHostGroupID($information["hg_name"]);
			return $hg_id;
		}
	}
	
	public function getHostGroupID($hg_name = NULL) {
		if (!isset($hg_name))
			return;
			
		$request = "SELECT hg_id FROM hostgroup WHERE hg_name LIKE '$hg_name'";
		$DBRESULT =& $this->DB->query($request);
		$data =& $DBRESULT->fetchRow();
		return $data["hg_id"];
	}
}
?>