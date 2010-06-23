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
 
class CentreonServiceGroup {
	private $DB;
	
	public function __construct($DB) {
		$this->DB = $DB;
	}

	/*
	 * Check host existance
	 */
	protected function serviceGroupExists($name) {
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
	
	protected function checkParameters($options) {
		if (!isset($options) || $options == "") {
			print "No options defined. $str\n";
			$this->return_code = 1;
			return 1;
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
	
	/* ****************************************
	 *  Delete Action
	 */
	 
	public function del($name) {
		$request = "DELETE FROM servicegroup WHERE sg_name LIKE '".htmlentities($name, ENT_QUOTES)."'";
		$DBRESULT =& $this->DB->query($request);
		$this->return_code = 0;
		return;
	}
	
	/* ****************************************
	 * Dislay all SG
	 */
	public function show($search = NULL) {
		$searchStr = "";
		if (isset($search) && $search != "") {
			$searchStr = " WHERE sg_name LILE '%".htmlentities($search, ENT_QUOTES)."%'";
		}
		$request = "SELECT sg_name, sg_alias FROM servicegroup $searchStr ORDER BY sg_name";
		$DBRESULT =& $this->DB->query($request);
		while ($data =& $DBRESULT->fetchRow()) {
			print html_entity_decode($data["sg_name"], ENT_QUOTES).";".html_entity_decode($data["sg_alias"], ENT_QUOTES)."\n";
		}
		$DBRESULT->free();
		
	}
	
	/* ****************************************
	 * Add Action
	 */
	
	public function add($options) {
		
		$info = split(";", $options);
		
		if (!$this->serviceGroupExists($info[0])) {
			$convertionTable = array(0 => "sg_name", 1 => "sg_alias");
			$informations = array();
			foreach ($info as $key => $value) {
				$informations[$convertionTable[$key]] = $value;
			}
			$this->addServiceGroup($informations);
		} else {
			print "Servicegroup ".$info[0]." already exists.\n";
			$this->return_code = 1;
			return;
		}
	}
	
	protected function addServiceGroup($information) {
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
	
	/* ****************************************
	 * Add Action
	 */
	
	public function setParam($options) {
		$elem = split(";", $options);
		return $this->setParamServiceGroup($elem[0], $elem[1], $elem[2]);
	}
	
	protected function setParamServiceGroup($sg_name, $parameter, $value) {
		
		$value = htmlentities($value, ENT_QUOTES);
		
		$sg_id = $this->getServiceGroupID($sg_name);
		if ($sg_id) {
			$request = "UPDATE servicegroup SET $parameter = '$value' WHERE sg_id = '$sg_id'";
			$DBRESULT =& $this->DB->query($request);
			if ($DBRESULT) {
				return 0;
			} else {
				return 1;
			}
		} else {
			print "Service group doesn't exists. Please check your arguments\n";
			return 1;	
		}
	}
	
	/* **************************************
	 * Add childs
	 */
	 
	public function addChild($options) {
		$elem = split(";", $options);
		return $this->addChildServiceGroup($elem[0], $elem[1], $elem[2]);
	}
	 
	protected function addChildServiceGroup($sg_name, $child_host, $child_service) {
		
		require_once "./class/centreonHost.class.php";
		require_once "./class/centreonService.class.php";

		/*
		 * Get host Child informations
		 */
		$host = new CentreonHost($this->DB, "HOST");
		$host_id = $host->getHostID(htmlentities($child_host, ENT_QUOTES));
		
		/*
		 * Get service Child information
		 */
		$service = new CentreonService($this->DB, "SERVICE");
		$service_id = $service->getServiceID($host_id, htmlentities($child_service, ENT_QUOTES));

		/*
		 * Add link.
		 */				
		$sg_id = $this->getServiceGroupID($sg_name);
		if ($sg_id && $host_id && $service_id) {
			$request = "DELETE FROM servicegroup_relation WHERE host_host_id = '$host_id' AND service_service_id = '$service_id' AND servicegroup_sg_id = '$sg_id'";
			$DBRESULT =& $this->DB->query($request);
			$request = "INSERT INTO servicegroup_relation (host_host_id, service_service_id, servicegroup_sg_id) VALUES ('$host_id', '$service_id', '$sg_id')";
			$DBRESULT =& $this->DB->query($request);
			if ($DBRESULT) {
				return 0;
			} else {
				return 1;
			}
		} else {
			print "Servicegroup or host doesn't exists. Please check your arguments\n";
			return 1;	
		}
	}
	
	/* **************************************
	 * Add childs
	 */
	 
	public function delChild($options) {
		$elem = split(";", $options);
		return $this->delChildServiceGroup($elem[0], $elem[1], $elem[2]);
	}
	
	protected function delChildServiceGroup($sg_name, $child_host, $child_service) {
		
		require_once "./class/centreonHost.class.php";
		require_once "./class/centreonService.class.php";
		
		/*
		 * Get host Child informations
		 */
		$host = new CentreonHost($this->DB, "HOST");
		$host_id = $host->getHostID(htmlentities($child_host, ENT_QUOTES));
		
		/*
		 * Get service Child information
		 */
		$service = new CentreonService($this->DB, "SERVICE");
		$service_id = $service->getServiceID($host_id, htmlentities($child_service, ENT_QUOTES));

		/*
		 * Add link.
		 */				
		$sg_id = $this->getServiceGroupID($sg_name);
		if ($sg_id && $host_id && $service_id) {
			$request = "DELETE FROM servicegroup_relation WHERE host_host_id = '$host_id' AND service_service_id = '$service_id' AND servicegroup_sg_id = '$sg_id'";
			$DBRESULT =& $this->DB->query($request);
			if ($DBRESULT) {
				return 0;
			} else {
				return 1;
			}
		} else {
			print "Servicegroup or host doesn't exists. Please check your arguments\n";
			return 1;	
		}
	}
}
?>