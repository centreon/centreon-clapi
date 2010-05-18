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
 * SVN : $URL$
 * SVN : $Id$
 *
 */
 
class CentreonHost {
	private $DB;
	private $host_name;
	private $host_id;
		
	public function __construct($DB) {
		$this->DB = $DB;
	}
	
	/*
	 * Check host existance
	 */
	public function hostExists($name) {
		if (!isset($name))
			return 0;
		
		/*
		 * Get informations
		 */
		$DBRESULT =& $this->DB->query("SELECT host_name, host_id FROM host WHERE host_name = '".htmlentities($name, ENT_QUOTES)."' AND host_register = '1'");
		if ($DBRESULT->numRows() >= 1) {
			$host =& $DBRESULT->fetchRow();
			$DBRESULT->free();
			return $host["host_id"];
		} else {
			return 0;
		}
	}

	/*
	 * Delete Host
	 */
	public function delHost($host_name) {
		$request = "DELETE FROM host WHERE host_name LIKE '$host_name'";
		$DBRESULT =& $this->DB->query($request);
		$this->return_code = 0;
		return;
	}
	
	/*
	 * Get Name of an host
	 */
	public function getHostName($host_id) {
		$request = "SELECT host_name FROM host WHERE host_id = '$host_id'";
		$DBRESULT =& $this->DB->query($request);
		$data =& $DBRESULT->fetchRow();
		$DBRESULT->free();
		
		if (isset($data["host_name"]) && $data["host_name"])
			return $data["host_name"];
		else
			return "";
	}

	/*
	 * Deploy all services of an host
	 */
	public function deployServiceTemplates($host_id, $objService, $tpl_id = NULL) {
		if (!isset($tpl_id))
			$tpl_id = $host_id;
		if (isset($tpl_id) && $tpl_id) {
			$request = "SELECT host_tpl_id FROM host_template_relation WHERE host_host_id = '$tpl_id'";
			$DBRESULT =& $this->DB->query($request);
			while ($data =& $DBRESULT->fetchRow()) {
				/*
				 * Check if service is linked
				 */
				$request2 = "SELECT service_service_id FROM host_service_relation WHERE host_host_id = '".$data["host_tpl_id"]."'";
				$DBRESULT2 =& $this->DB->query($request2);
				while ($svc =& $DBRESULT2->fetchRow()) {
					$name = $objService->getServiceAlias($svc["service_service_id"]);
					if (!$objService->testServiceExistence($name, $host_id)) {
						$objService->addService(array("service_description" => $name, "template" => $svc["service_service_id"], "host" => $host_id, "macro" => array()));
					}
				}
				$DBRESULT2->free();
				$this->deployServiceTemplates($host_id, $objService, $data["host_tpl_id"]);
			}
			$DBRESULT->free();
		}
	}

	/*
	 * Add an host
	 */
	public function addHost($information) {
		if (!isset($information["host_name"]) || !isset($information["host_address"]) || !isset($information["host_template"]) || !isset($information["host_poller"])) {
			return 0;
		} else {
			if (!isset($information["host_alias"]) || $information["host_alias"] == "")
				$information["host_alias"] = $information["host_name"];
			/*
			 * Insert Host
			 */
			$request = 	"INSERT INTO host (host_name, host_alias, host_address, host_register, host_activate, host_active_checks_enabled, host_passive_checks_enabled, host_checks_enabled, host_obsess_over_host, host_check_freshness, host_event_handler_enabled, host_flap_detection_enabled, host_process_perf_data, host_retain_status_information, host_retain_nonstatus_information, host_notifications_enabled) " .
						"VALUES ('".htmlentities(trim($information["host_name"]), ENT_QUOTES)."', '".htmlentities(trim($information["host_alias"]), ENT_QUOTES)."', '".htmlentities(trim($information["host_address"]), ENT_QUOTES)."', '1', '1', '2', '2', '2', '2', '2', '2', '2', '2', '2', '2', '2')";
			$this->DB->query($request);
			$host_id = $this->getHostID(htmlentities($information["host_name"], ENT_QUOTES));
			
			/*
			 * Insert Template Relation
			 */
			$request = "INSERT INTO host_template_relation (host_tpl_id, host_host_id) VALUES ('".$information["host_template"]."', '".$host_id."')";
			$this->DB->query($request);
			
			/*
			 * Insert Extended Info
			 */
			$request = "INSERT INTO extended_host_information (host_host_id) VALUES ('".$host_id."')";
			$this->DB->query($request);
			
			/*
			 * Insert Host Poller
			 */
			$this->setPoller($host_id, $information["host_poller"]);
			return $host_id;
		}
	}
	
	/*
	 * Set Poller link for an host 
	 */
	public function setPoller($host_id, $poller_id) {
		if (!isset($host_id) || !isset($poller_id)) {
			print "Bad parameters\n";
			exit(1);
		} else {
			$request = "INSERT INTO ns_host_relation (nagios_server_id, host_host_id) VALUES ('".$poller_id."', '".$host_id."')";
			$this->DB->query($request);
			return 0;			
		}
	}
	
	/*
	 * Free Poller link
	 */
	public function unsetPoller($host_id) {
		if (!isset($host_id)) {
			print "Bad parameters\n";
			exit(1);
		} else {
			$request = "DELETE FROM ns_host_relation WHERE host_host_id = '".$host_id."'";
			$this->DB->query($request);
			return 0;			
		}
	}
	
	/*
	 * Get id of host
	 */
	public function getHostID($name) {
		$request = "SELECT host_id FROM host WHERE host_name = '".trim($name)."' AND host_register = '1'";
		$DBRESULT =& $this->DB->query($request);
		if ($DBRESULT->numRows()) {
			$info =& $DBRESULT->fetchRow();
			$DBRESULT->free();
			$this->host_id = $info["host_id"];
			return $info["host_id"];
		} else {
			return 0;
		}
	}
	
	/*
	 * List all hosts
	 */
	public function listHost() {
		$request = "SELECT host_id, host_address, host_name, host_alias FROM host WHERE host_register = '1' ORDER BY host_name";
		$DBRESULT =& $this->DB->query($request);
		while ($data =& $DBRESULT->fetchRow()) {
			print $data["host_id"].";".$data["host_name"].";".$data["host_alias"].";".$data["host_address"]."\n";
		}
		$DBRESULT->free();
		unset($data);
	}
	
	/*
	 * Set parents
	 */
	public function setParent($child_name, $parent_name) {
		if ($child_name == $parent_name) {
			print "Error in arguments. A host cannot be the parent of himself....\n";
			return 1;
		}
		
		$request = "SELECT host_id FROM host WHERE host_name IN ('$child_name', '$parent_name')";				
		$DBRESULT =& $this->DB->query($request);
		if ($DBRESULT->numRows() == 2) {
			/*
			 * Check Circular link
			 */
			$request = 	"SELECT * FROM host_hostparent_relation " .
						"WHERE host_parent_hp_id IN (SELECT host_id FROM host WHERE host_name LIKE '$child_name') " .
						"AND host_host_id IN (SELECT host_id FROM host WHERE host_name LIKE '$parent_name')";
			$DBRESULT =& $this->DB->query($request);
			if ($DBRESULT->numRows() != 0) {
				print "Circular parent link. Can process this action.\n";
				return 1;	
			}
			
			/*
			 * Check parent state
			 */
			$request = 	"SELECT * FROM host_hostparent_relation " .
						"WHERE host_host_id IN (SELECT host_id FROM host WHERE host_name LIKE '$child_name') " .
						"AND host_parent_hp_id IN (SELECT host_id FROM host WHERE host_name LIKE '$parent_name')";
			$DBRESULT =& $this->DB->query($request);
			if ($DBRESULT->numRows() != 0) {
				print "Host $child_name is already the child of host $parent_name.\n";
				return 1;	
			}
			
			/*
			 * Insert all data
			 */
			$request = 	"INSERT INTO host_hostparent_relation (host_parent_hp_id, host_host_id) " .
						"VALUES ((SELECT host_id FROM host WHERE host_name LIKE '$parent_name'), (SELECT host_id FROM host WHERE host_name LIKE '$child_name'))";
						
			$DBRESULT =& $this->DB->query($request);
			return 0;		
		} else {
			print "Child or parent host unknown.\n";
			return 1;	
		}
	}
	
	/*
	 * Set Parameters
	 */
	public function setParameter($host_name, $parameter, $value) {
		/*
		 * Parameters List
		 */
		$tabName = array(
			"name" => "host",
			"alias" => "host",
			"address" => "host",
			"poller" => "host",
			"community" => "host",
			"version" => "host",
			"tpcheck" => "host",
			"url" => "extended_host_information",
			"actionurl" => "extended_host_information",
		);
		
		/*
		 * Set Real field name
		 */
		$realNameField = array(
			"name" => "host_name",
			"alias" => "host_alias",
			"address" => "host_address",
			"community" => "host_snmp_community",
			"version" => "host_snmp_version",
			"tpcheck" => "timeperiod_tp_id",
			"url" => "ehi_notes_url",
			"actionurl" => "ehi_action_url",
		);
		
		/*
		 * Host or host_extentended info
		 */
		$host_id_field = array("host" => "host_id", "extended_host_information" => "host_host_id");
		
		if (!isset($tabName[$parameter])) {
			print "Unknown parameters for host.\n";
			return 1;
		}
		
		/*
		 * Check timeperiod case
		 */
		if ($parameter == "tpcheck") {
			$request = "SELECT tp_id FROM timeperiod WHERE tp_name LIKE '".htmlentities($value, ENT_QUOTES)."'";
			$DBRESULT =& $this->DB->query($request);
			$data = $DBRESULT->fetchRow();
			$value = $data["tp_id"];
		}

		/*
		 * Check poller case
		 */
		if ($parameter == "poller") {
			$host_id = $this->getHostID(htmlentities($host_name, ENT_QUOTES));
			$this->unsetPoller($host_id);
			$request = "SELECT id FROM nagios_server WHERE name LIKE '".htmlentities($value, ENT_QUOTES)."'";
			$DBRESULT =& $this->DB->query($request);
			$data = $DBRESULT->fetchRow();
			return $this->setPoller($host_id, $data["id"]);
		}

		$request = "SELECT host_id FROM host WHERE host_name IN ('$host_name')";				
		$DBRESULT =& $this->DB->query($request);
		if ($DBRESULT->numRows() == 1) {
			if ($value != "NULL" && $value != "'NULL'") {
				$value = "'".$value."'";
			}
			if ($tabName[$parameter] == "host") {
				$request = "UPDATE ".$tabName[$parameter]." SET ".$realNameField[$parameter]." = ".$value." WHERE host_name LIKE '$host_name'";
			} else {
				$request = "UPDATE ".$tabName[$parameter]." SET ".$realNameField[$parameter]." = ".$value." WHERE ".$host_id_field[$tabName[$parameter]]." = (SELECT host_id FROM host WHERE host_name LIKE '$host_name')";
			}
			$DBRESULT =& $this->DB->query($request);
		} else {
			print "Unknown host : $host_name.\n";
			return 1;
		}
	} 
	
	/*
	 * Set host macro
	 */
	public function setMacro($host_name, $macro_name, $macro_value) {
		if (!isset($host_name) || !isset($macro_name)) {
			print "Bad parameters\n";
			return 1;
		}
		
		$macro_name = strtoupper($macro_name);
		
		$host_id = $this->getHostID(htmlentities($host_name, ENT_QUOTES));
		$request = "SELECT COUNT(*) FROM on_demand_macro_host WHERE host_host_id = '".htmlentities($host_id, ENT_QUOTES)."' AND host_macro_name LIKE '".htmlentities($macro_name, ENT_QUOTES)."'";
		$DBRESULT =& $this->DB->query($request); 
		$data =& $DBRESULT->fetchRow();
		if ($data["COUNT(*)"]) {
			$request = "UPDATE on_demand_macro_host SET host_macro_value = '".htmlentities($macro_value, ENT_QUOTES)."' WHERE host_host_id = '".htmlentities($host_id, ENT_QUOTES)."' AND host_macro_name LIKE '".htmlentities($macro_name, ENT_QUOTES)."' LIMIT 1";
			$DBRESULT =& $this->DB->query($request);
			return 0;
		} else {
			$request = "INSERT INTO on_demand_macro_host (host_host_id, host_macro_value, host_macro_name) VALUES ('".htmlentities($host_id, ENT_QUOTES)."', '".htmlentities($macro_value, ENT_QUOTES)."', '".htmlentities($macro_name, ENT_QUOTES)."')";
			$DBRESULT =& $this->DB->query($request);
			return 0;
		}
	}
	
	/*
	 * Delete host macro
	 */
	public function delMacro($host_name, $macro_name) {
		if (!isset($host_name) || !isset($macro_name)) {
			print "Bad parameters\n";
			return 1;
		}
		
		$macro_name = strtoupper($macro_name);

		$host_id = $this->getHostID(htmlentities($host_name, ENT_QUOTES));
		$request = "DELETE FROM on_demand_macro_host WHERE host_host_id = '".htmlentities($host_id, ENT_QUOTES)."' AND host_macro_name LIKE '".htmlentities($macro_name, ENT_QUOTES)."'";
		$DBRESULT =& $this->DB->query($request); 
		return 0;	
	}
	 
}
 
?>