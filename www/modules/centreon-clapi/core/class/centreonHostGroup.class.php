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
 
class CentreonHostGroup {
	private $DB;
	
	public function __construct($DB) {
		$this->DB = $DB;
	}

	/*
	 * Check host existance
	 */
	protected function hostGroupExists($name) {
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
	
	protected function checkParameters($options) {
		if (!isset($options) || $options == "") {
			print "No options defined.\n";
			$this->return_code = 1;
			return 1;
		}
	}
	
	protected function getHostGroupID($hg_name = NULL) {
		if (!isset($hg_name))
			return;
			
		$request = "SELECT hg_id FROM hostgroup WHERE hg_name LIKE '$hg_name'";
		$DBRESULT =& $this->DB->query($request);
		$data =& $DBRESULT->fetchRow();
		return $data["hg_id"];
	}
	
	public function getHostGroupHosts($hg_id) {
		$hostList = array();
		$request = "SELECT host_host_id FROM hostgroup_relation WHERE hostgroup_hg_id = '".(int)$hg_id."'";
		$DBRESULT =& $this->DB->query($request);
		while ($hg = $DBRESULT->fetchRow()) {
			$hostList[$hg["host_host_id"]] = $hg["host_host_id"];
		}
		$DBRESULT->free();
		return $hostList;
	}
	
	public function del($options) {
		
		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}
		
		$request = "DELETE FROM hostgroup WHERE hg_name LIKE '".htmlentities($options, ENT_QUOTES)."'";
		$DBRESULT =& $this->DB->query($request);
		$this->return_code = 0;
		return;
	}
	
	public function show($search = NULL) {
		$searchStr = "";
		if (isset($search) && $search != "") {
			$searchStr = " WHERE hg_name LIKE '%".htmlentities($search, ENT_QUOTES)."%'";
		}
		$request = "SELECT hg_id, hg_name, hg_alias FROM hostgroup $searchStr ORDER BY hg_name";
		$DBRESULT =& $this->DB->query($request);
		$i = 0;
		while ($data =& $DBRESULT->fetchRow()) {
			if ($i == 0) {
				print "id;name;alias;members\n";
			}
			print $data["hg_id"].";".html_entity_decode($data["hg_name"], ENT_QUOTES).";".html_entity_decode($data["hg_alias"], ENT_QUOTES).";";
			
			$members = "";
			$request = "SELECT host_name FROM host, hostgroup_relation WHERE hostgroup_hg_id = '".$data["hg_id"]."' AND host_host_id = host_id ORDER BY host_name";
			$DBRESULT2 =& $this->DB->query($request);
			while ($m =& $DBRESULT2->fetchRow()) {
				if ($members != "") {
					$members .= ",";
				}
				$members .= $m["host_name"];				
			}
			$DBRESULT2->free();
			print $members."\n";
			
			$i++;
		}
		$DBRESULT->free();
	}

	/* *************************************
	 * Add functions
	 */
	public function add($options) {
		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}
		
		/*
		 * Split options
		 */
		$info = split(";", $options);

		if (!$this->hostGroupExists($info[0])) {
			$convertionTable = array(0 => "hg_name", 1 => "hg_alias");
			$informations = array();
			foreach ($info as $key => $value) {
				$informations[$convertionTable[$key]] = $value;
			}
			$this->addHostGroup($informations);
			unset($informations);
		} else {
			print "Hostgroup ".$info[0]." already exists.\n";
			$this->return_code = 1;
			return;
		}
	}

	protected function addHostGroup($information) {
		if (!isset($information["hg_name"])) {
			print "No information received\n";
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
	
	/* ***************************************
	 * Set params
	 */
	public function setParam($options) {
		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}
		
		$elem = split(";", $options);
		return $this->setParamHostGroup($elem[0], $elem[1], $elem[2]);
	}
	
	protected function setParamHostGroup($hg_name, $parameter, $value) {
		
		$value = htmlentities($value, ENT_QUOTES);
		
		$hg_id = $this->getHostGroupID($hg_name);
		if ($hg_id) {
			$request = "UPDATE hostgroup SET $parameter = '$value' WHERE hg_id = '$hg_id'";
			$DBRESULT =& $this->DB->query($request);
			if ($DBRESULT) {
				return 0;
			} else {
				return 1;
			}
		} else {
			print "Hostgroup doesn't exists. Please check your arguments\n";
			return 1;	
		}
	}
	
	/* ************************************
	 * Add Child
	 */
	
	public function addChild($options) {
		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}
		
		$elem = split(";", $options);
		return $this->return_code = $this->addChildHostGroup($elem[0], $elem[1]);
	} 
	
	protected function addChildHostGroup($hg_name, $child) {
		
		require_once "./class/centreonHost.class.php";
		
		/*
		 * Get Child informations
		 */
		$host = new CentreonHost($this->DB, "HOST");
		$host_id = $host->getHostID(htmlentities($child, ENT_QUOTES));
		
		$hg_id = $this->getHostGroupID($hg_name);
		if ($hg_id && $host_id) {
			$request = "DELETE FROM hostgroup_relation WHERE host_host_id = '$host_id' AND hostgroup_hg_id = '$hg_id'";
			$DBRESULT =& $this->DB->query($request);
			$request = "INSERT INTO hostgroup_relation (host_host_id, hostgroup_hg_id) VALUES ('$host_id', '$hg_id')";
			$DBRESULT =& $this->DB->query($request);
			if ($DBRESULT) {
				return 0;
			} else {
				return 1;
			}
		} else {
			print "Hostgroup or host doesn't exists. Please check your arguments\n";
			return 1;	
		}
	}
	
	/* ************************************
	 * Del Child
	 */
	
	public function delChild($options) {
		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}
		
		$elem = split(";", $options);
		return $this->return_code = $this->delChildHostGroup($elem[0], $elem[1]);
	} 
	
	protected function delChildHostGroup($hg_name, $child) {
		
		require_once "./class/centreonHost.class.php";
		
		/*
		 * Get Child informations
		 */
		$host = new CentreonHost($this->DB, "HOST");
		$host_id = $host->getHostID(htmlentities($child, ENT_QUOTES));
		
		$hg_id = $this->getHostGroupID($hg_name);
		if ($hg_id && $host_id) {
			$request = "DELETE FROM hostgroup_relation WHERE host_host_id = '$host_id' AND hostgroup_hg_id = '$hg_id'";
			$DBRESULT =& $this->DB->query($request);
			if ($DBRESULT) {
				return 0;
			} else {
				return 1;
			}
		} else {
			print "Hostgroup or host doesn't exists. Please check your arguments\n";
			return 1;	
		}
	}
}
?>