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
 * SVN : $URL$
 * SVN : $Id$
 *
 */
 
class CentreonHost {
	private $DB;
	private $host_name;
	private $host_id;
	private $register;
	private $cg;
		
	public function __construct($DB, $objName) {
		$this->DB = $DB;
		$this->register = 1;

		if (strtoupper($objName) == "HTPL") {
			$this->setTemplateFlag();
		}
		
		/*
		 * Create ContactGroup object
		 */
		require_once "./class/centreonContactGroup.class.php";
		$this->cg = new CentreonContactGroup($this->DB, "CG");
		
	}
	
	protected function setTemplateFlag() {
		$this->register = 0;
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
		$DBRESULT =& $this->DB->query("SELECT host_name, host_id FROM host WHERE host_name = '".htmlentities($name, ENT_QUOTES)."' AND host_register = '".$this->register."'");
		if ($DBRESULT->numRows() >= 1) {
			$host =& $DBRESULT->fetchRow();
			$DBRESULT->free();
			return $host["host_id"];
		} else {
			return 0;
		}
	}
	
	protected function checkParameters($options) {
		if (!isset($options) || $options == "") {
			print "No options defined. $str\n";
			return 1;
		}
	}
	
	protected function validateName($name) {
		if (preg_match('/^[0-9a-zA-Z\_\-\ \/\\\.]*$/', $name, $matches)) {
			return $this->checkNameformat($name);
		} else {
			print "Name '$name' doesn't match with Centreon naming rules.\n";
			exit (1);	
		}
	}
	
	protected function checkNameformat($name) {
		if (strlen($name) > 25) {
			print "Warning: host name reduce to 25 caracters.\n";
		}
		return sprintf("%.25s", $name);
	}
	
	/*
	 * Get Poller id
	 */
	protected function getPollerID($name) {
		$request = "SELECT id FROM nagios_server WHERE name LIKE '".trim(htmlentities($name, ENT_QUOTES))."'";
		$DBRESULT =& $this->DB->query($request);
		if ($DBRESULT->numRows()) {
			$info =& $DBRESULT->fetchRow();
			$DBRESULT->free();
			return $info["id"];
		} else {
			return 0;
		}
	}
	
	/*
	 * Get id of host
	 */
	public function getHostID($name) {
		$request = "SELECT host_id FROM host WHERE host_name = '".trim($this->encode($name))."' AND host_register = '".$this->register."'";
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
	 * Get Name of an host
	 */
	public function getHostName($host_id, $readable = NULL) {
		$request = "SELECT host_name FROM host WHERE host_id = '$host_id'";
		$DBRESULT =& $this->DB->query($request);
		$data =& $DBRESULT->fetchRow();
		$DBRESULT->free();
		
		if (isset($data["host_name"]) && $data["host_name"]) {
			if (isset($readable) && $readable) {
				$data["host_name"] = $this->decode($data["host_name"]);
			}
			return $data["host_name"];
		} else {
			return "";
		}
	}
	
	protected function encode($str) {
		$str = str_replace("/", "#S#", $str);
		$str = str_replace("\\", "#BS#", $str);
		return $str;			
	}
	
	protected function decode($str) {
		$str = str_replace("#S#", "/", $str);
		$str = str_replace("#BS#", "\\", $str);
		return $str;			
	}
	
	
	/* ***********************************
	 * Add functions
	 */
	public function add($options) {
		
		$this->checkParameters($options);
		
		$svc = new CentreonService($this->DB, "Service");
		$info = split(";", $options);
		/*
		 * Check host_name / host_alias rules
		 */
		$info[0] = $this->validateName($info[0]);
		
		if (!$this->hostExists($info[0])) {
			if ($this->register) {
				$convertionTable = array(0 => "host_name", 1 => "host_alias", 2 => "host_address", 3 => "host_template", 4 => "host_poller", 5 => "hostgroup");
				$informations = array();
				foreach ($info as $key => $value) {
					$informations[$convertionTable[$key]] = $value;
				}			
				$host_id = $this->addHost($informations);
				$this->deployServiceTemplates($host_id, $svc);				
			} else {
				$convertionTable = array(0 => "host_name", 1 => "host_alias", 2 => "host_address", 3 => "host_template");
				$informations = array();
				foreach ($info as $key => $value) {
					$informations[$convertionTable[$key]] = $value;
				}			
				$host_id = $this->addHostTemplate($informations);
			}
		} else {
			if ($this->register) {
				$type = "";
			} else {
				$type = " template";
			}
			
			print "Host$type ".$info[0]." already exists.\n";
			$this->return_code = 1;
			return;
		}
	}

	/*
	 * Add an host
	 */
	protected function addHost($information) {
		if (!isset($information["host_name"]) || !isset($information["host_address"]) || !isset($information["host_template"]) || !isset($information["host_poller"])) {
			return 0;
		} else {
			if (!isset($information["host_alias"]) || $information["host_alias"] == "") {
				$information["host_alias"] = $information["host_name"];
			}
			
			/*
			 * Insert Host
			 */
			$request = 	"INSERT INTO host (host_name, host_alias, host_address, host_register, host_activate, host_active_checks_enabled, host_passive_checks_enabled, host_checks_enabled, host_obsess_over_host, host_check_freshness, host_event_handler_enabled, host_flap_detection_enabled, host_process_perf_data, host_retain_status_information, host_retain_nonstatus_information, host_notifications_enabled) " .
						"VALUES ('".htmlentities(trim($this->encode($information["host_name"])), ENT_QUOTES)."', '".htmlentities(trim($this->encode($information["host_alias"])), ENT_QUOTES)."', '".htmlentities(trim($information["host_address"]), ENT_QUOTES)."', '".$this->register."', '1', '2', '2', '2', '2', '2', '2', '2', '2', '2', '2', '2')";
			$this->DB->query($request);
			
			/*
			 * Get host ID.
			 */
			$host_id = $this->getHostID(htmlentities($information["host_name"], ENT_QUOTES));
			
			/*
			 * Insert Template Relation
			 */
			if ($information["host_template"]) {
				if (strstr($information["host_template"], ",")) {
					$tab = split(",", $information["host_template"]);
					foreach ($tab as $hostTemplate) {
						$request = "INSERT INTO host_template_relation (host_tpl_id, host_host_id) VALUES ((SELECT host_id FROM host WHERE host_name LIKE '".$hostTemplate."'), '".$host_id."')";
						$this->DB->query($request);
					}
				} else {
					$request = "INSERT INTO host_template_relation (host_tpl_id, host_host_id) VALUES ((SELECT host_id FROM host WHERE host_name LIKE '".$information["host_template"]."'), '".$host_id."')";
					$this->DB->query($request);
				}
			}
			
			/*
			 * Insert hostgroup relation
			 */
			if ($information["hostgroup"]) {
				if (strstr($information["hostgroup"], ",")) {
					$tab = split(",", $information["hostgroup"]);
					foreach ($tab as $hostgroup_name) {
						$request = "INSERT INTO hostgroup_relation (hostgroup_hg_id, host_host_id) values ((SELECT hg_id FROM hostgroup WHERE hg_name LIKE '".$hostgroup_name."'),".$host_id.")";
						$this->DB->query($request);
					}
				} else {
					$request = "INSERT INTO hostgroup_relation (hostgroup_hg_id, host_host_id) values ((SELECT hg_id FROM hostgroup WHERE hg_name LIKE '".$information["hostgroup"]."'),".$host_id.")";
					$this->DB->query($request);
				}
			}
						
			/*
			 * Insert Extended Info
			 */
			$request = "INSERT INTO extended_host_information (host_host_id) VALUES ('".$host_id."')";
			$this->DB->query($request);
			
			/*
			 * Insert Host Poller
			 */
			$this->setPoller($host_id, $this->getPollerID($information["host_poller"]));
			return $host_id;
		}
	}
	
	/*
	 * Add an host template
	 */
	protected function addHostTemplate($information) {
		if (!isset($information["host_name"]) || !isset($information["host_address"]) || !isset($information["host_template"])) {
			return 0;
		} else {
			if (!isset($information["host_alias"]) || $information["host_alias"] == "") {
				$information["host_alias"] = $information["host_name"];
			}
			
			/*
			 * Insert Host
			 */
			$request = 	"INSERT INTO host (host_name, host_alias, host_address, host_register, host_activate, host_active_checks_enabled, host_passive_checks_enabled, host_checks_enabled, host_obsess_over_host, host_check_freshness, host_event_handler_enabled, host_flap_detection_enabled, host_process_perf_data, host_retain_status_information, host_retain_nonstatus_information, host_notifications_enabled) " .
						"VALUES ('".htmlentities(trim($this->encode($information["host_name"])), ENT_QUOTES)."', '".htmlentities(trim($this->encode($information["host_alias"])), ENT_QUOTES)."', '".htmlentities(trim($information["host_address"]), ENT_QUOTES)."', '".$this->register."', '1', '2', '2', '2', '2', '2', '2', '2', '2', '2', '2', '2')";
			$this->DB->query($request);
			
			/*
			 * Get host ID.
			 */
			$host_id = $this->getHostID(htmlentities($information["host_name"], ENT_QUOTES));
			
			/*
			 * Insert Template Relation
			 */
			if ($information["host_template"]) {
				if (strstr($information["host_template"], ",")) {
					$tab = split(",", $information["host_template"]);
					foreach ($tab as $hostTemplate) {
						$request = "INSERT INTO host_template_relation (host_tpl_id, host_host_id) VALUES ((SELECT host_id FROM host WHERE host_name LIKE '".$hostTemplate."'), '".$host_id."')";
						$this->DB->query($request);
					}
				} else {
					$request = "INSERT INTO host_template_relation (host_tpl_id, host_host_id) VALUES ((SELECT host_id FROM host WHERE host_name LIKE '".$information["host_template"]."'), '".$host_id."')";
					$this->DB->query($request);
				}
			}
									
			/*
			 * Insert Extended Info
			 */
			$request = "INSERT INTO extended_host_information (host_host_id) VALUES ('".$host_id."')";
			$this->DB->query($request);
			
			return $host_id;
		}
	}
	
	/*
	 * Apply Template
	 */
	public function applyTPL($options) {
		
		$this->checkParameters($options);
		
		/*
		 * Create service class
		 */
		$svc = new CentreonService($this->DB, "Service");
		
		$host_id = $this->getHostID($options);
		$this->deployServiceTemplates($host_id, $svc);
	}

	/* *************************************
	 * Delete Host
	 */
	public function del($options) {
		
		$this->checkParameters($options);
		
		$request = "DELETE FROM host WHERE host_name LIKE '".htmlentities($this->decode($options), ENT_QUOTES)."'";
		$DBRESULT =& $this->DB->query($request);
		$this->return_code = 0;
		return;
	}
	
	/* *******************************************
	 * Macro Management
	 */
	public function setMacro($options) {
		
		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}
		
		$info = split(";", $options);
		$return_code = $this->setMacroHost($info[0], $info[1], $info[2]);
		return $return_code;
	}
	
	public function delMacro($options) {
		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}
		
		$info = split(";", $options);
		$return_code = $this->delMacroHost($info[0], $info[1]);
		return $return_code;
	}

	/* ******************************************
	 * Deploy all services of an host
	 */
	protected function deployServiceTemplates($host_id, $objService, $tpl_id = NULL) {
		if (!isset($tpl_id)) {
			$tpl_id = $host_id;
		}
		 
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
	
	/* ***********************************************
	 * List all hosts or templates
	 */
	public function show($host_name = NULL) {
		$search = "";
		if (isset($host_name)) {
			$search = " AND (host_name like '%".htmlentities($host_name, ENT_QUOTES)."%' OR host_alias LIKE '%".htmlentities($host_name, ENT_QUOTES)."%') ";
		}
		
		$request = "SELECT host_id, host_address, host_name, host_alias FROM host WHERE host_register = '".$this->register."' $search ORDER BY host_name";
		$DBRESULT =& $this->DB->query($request);
		$i = 0;
		while ($data =& $DBRESULT->fetchRow()) {
			if ($i == 0) {
				print "id;name;alias;address\n";
			}
			print $this->decode($data["host_id"]).";".$this->decode($data["host_name"]).";".$data["host_alias"].";".$data["host_address"]."\n";
			$i++;
		}
		$DBRESULT->free();
		unset($data);
	}
	
	/* *********************************************
	 * Set parents
	 */
	public function setParent($options) {
		
		$this->checkParameters("Cannot set parents for host.");
		
		$elem = split(";", $options);
		if (strstr($elem[1], ",")) {
			$elem2 = split(",", $elem[1]);
			foreach ($elem2 as $value) {
				$exitcode = $this->setParentHost($elem[0], $value);
				if ($exitcode != 0) {
					return $exitcode;
				}
			}			
		} else {
			$exitcode = $this->setParentHost($elem[0], $elem[1]);		
		}
		return $exitcode;
	} 
	
	protected function setParentHost($child_name, $parent_name) {
		if ($this->register == 0) {
			return ;
		}
		
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
	
	
	public function unsetParent($options) {
		
		$check = $this->checkParameters("Cannot unset parents for host.");
		if ($check) {
			return $check;
		}
		$elem = split(";", $options);
		$exitcode = $this->unsetParentHost($elem[0], $elem[1]);		
		return $exitcode;
	} 
	
	protected function unsetParentHost($child_name, $parent_name) {
		if ($this->register == 0) {
			return ;
		}
		
		if ($child_name == $parent_name) {
			print "Error in arguments. A host cannot be the parent of himself....\n";
			return 1;
		}
		
		$request = "SELECT host_id FROM host WHERE host_name IN ('$child_name', '$parent_name')";				
		$DBRESULT =& $this->DB->query($request);
		if ($DBRESULT->numRows() == 2) {
			/*
			 * Insert all data
			 */
			$request = 	"DELETE FROM host_hostparent_relation WHERE host_parent_hp_id IN (SELECT host_id FROM host WHERE host_name LIKE '$parent_name') AND host_host_id IN (SELECT host_id FROM host WHERE host_name LIKE '$child_name') ";
			$DBRESULT =& $this->DB->query($request);
			return 0;		
		} else {
			print "Child or parent host unknown.\n";
			return 1;	
		}
	}
	
	/* ***********************************************
	 * Parameters management
	 */
	public function setParam($options) {
		
		$this->checkParameters($options);
		
		$elem = split(";", $options);
		$exitcode = $this->setParameterHost($elem[0], $elem[1], $elem[2]);
		return $exitcode;
	}
	
	
	/*
	 * Set Parameters
	 */
	protected function setParameterHost($host_name, $parameter, $value) {
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
			print "Unknown parameter for host.\n";
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

		if ($parameter == "name") {
			$value = $this->validateName($value);
		}

		$request = "SELECT host_id FROM host WHERE host_name IN ('$host_name') AND host_register = '".$this->register."'";				
		$DBRESULT =& $this->DB->query($request);
		if ($DBRESULT->numRows() == 1) {
			if ($value != "NULL" && $value != "'NULL'") {
				$value = "'".$value."'";
			}
			if ($tabName[$parameter] == "host") {
				$request = "UPDATE ".$tabName[$parameter]." SET ".$realNameField[$parameter]." = ".$value." WHERE host_name LIKE '$host_name' AND host_register = '".$this->register."'";
			} else {
				$request = "UPDATE ".$tabName[$parameter]." SET ".$realNameField[$parameter]." = ".$value." WHERE ".$host_id_field[$tabName[$parameter]]." = (SELECT host_id FROM host WHERE host_name LIKE '$host_name')";
			}
			$DBRESULT =& $this->DB->query($request);
		} else {
			print "Unknown host : $host_name.\n";
			return 1;
		}
	} 
	
	/* **************************************
	 * Add host template 
	 */
	public function addTemplate($information) {
		$check = $this->checkParameters($information);
		if ($check) {
			return 1;
		}
		
		$elem = split(";", $information);
		$exitcode = $this->addTemplateHost($elem[0], $elem[1]);
		return $exitcode;
	}
	
	protected function addTemplateHost($host_name, $template) {
		if (isset($host_name) && $host_name != "" && isset($template) && $template != "") {
			
			$svc = new CentreonService($this->DB, "Service");
		
			$request = "SELECT * FROM host_template_relation " .
						"WHERE host_host_id = (SELECT host_id FROM host WHERE host_name LIKE '".$host_name."') " .
								"AND host_tpl_id = (SELECT host_id FROM host WHERE host_name LIKE '".$template."')";
			$DBRESULT = $this->DB->query($request);
			if ($DBRESULT->numRows() == 0) {
				/*
				 * Get Host ID
				 */
				$host_id = $this->getHostID($host_name);
				
				$request = "INSERT INTO host_template_relation (host_tpl_id, host_host_id) VALUES ((SELECT host_id FROM host WHERE host_name LIKE '".$template."'), '".$host_id."')";
				$this->DB->query($request);
				if ($this->register) {
					$this->deployServiceTemplates($host_id, $svc);
				}
			} else {
				print "Template already added.\n";
				return 1;
			}
		} else {
			print "Check parameters.\n";
			return 1;
		}
	}
	
	/* *******************************
	 * Delete host template
	 */
	public function delTemplate($information) {
		$check = $this->checkParameters($information);
		if ($check) {
			return 1;
		}
		
		$elem = split(";", $information);
		$exitcode = $this->delTemplateHost($elem[0], $elem[1]);
		return $exitcode;
	}
	
	protected function delTemplateHost($host_name, $template) {
		if (isset($host_name) && $host_name != "" && isset($template) && $template != "") {
			
			$svc = new CentreonService($this->DB, "Service");
		
			$request = "SELECT * FROM host_template_relation " .
						"WHERE host_host_id = (SELECT host_id FROM host WHERE host_name LIKE '".$host_name."') " .
								"AND host_tpl_id = (SELECT host_id FROM host WHERE host_name LIKE '".$template."')";
			$DBRESULT = $this->DB->query($request);
			if ($DBRESULT->numRows() == 1) {
				
				/*
				 * Get Host ID
				 */
				$host_id = $this->getHostID($host_name);
				
				$request = "DELETE FROM host_template_relation WHERE host_tpl_id IN (SELECT host_id FROM host WHERE host_name LIKE '".$template."') AND host_host_id = '".$host_id."'";
				$this->DB->query($request);
				return 0;
			} else {
				print "No link between host $host_name and template $template.\n";
				return 1;
			}
		} else {
			print "Check parameters.\n";
			return 1;
		}
	}
	
	/*
	 * Set host macro
	 */
	protected function setMacroHost($host_name, $macro_name, $macro_value) {
		if (!isset($host_name) || !isset($macro_name)) {
			print "Bad parameters\n";
			return 1;
		}
		
		$macro_name = strtoupper($macro_name);
		
		$host_id = $this->getHostID(htmlentities($host_name, ENT_QUOTES));
		$request = "SELECT COUNT(*) FROM on_demand_macro_host WHERE host_host_id = '".htmlentities($host_id, ENT_QUOTES)."' AND host_macro_name LIKE '\$_HOST".htmlentities($macro_name, ENT_QUOTES)."\$'";
		$DBRESULT =& $this->DB->query($request); 
		$data =& $DBRESULT->fetchRow();
		if ($data["COUNT(*)"]) {
			$request = "UPDATE on_demand_macro_host SET host_macro_value = '".htmlentities($macro_value, ENT_QUOTES)."' WHERE host_host_id = '".htmlentities($host_id, ENT_QUOTES)."' AND host_macro_name LIKE '\$_HOST".htmlentities($macro_name, ENT_QUOTES)."\$' LIMIT 1";
			$DBRESULT =& $this->DB->query($request);
			return 0;
		} else {
			$request = "INSERT INTO on_demand_macro_host (host_host_id, host_macro_value, host_macro_name) VALUES ('".htmlentities($host_id, ENT_QUOTES)."', '".htmlentities($macro_value, ENT_QUOTES)."', '\$_HOST".htmlentities($macro_name, ENT_QUOTES)."\$')";
			$DBRESULT =& $this->DB->query($request);
			return 0;
		}
	}
	
	/*
	 * Delete host macro
	 */
	protected function delMacroHost($host_name, $macro_name) {
		if (!isset($host_name) || !isset($macro_name)) {
			print "Bad parameters\n";
			return 1;
		}
		
		$macro_name = strtoupper($macro_name);

		$host_id = $this->getHostID(htmlentities($host_name, ENT_QUOTES));
		$request = "DELETE FROM on_demand_macro_host WHERE host_host_id = '".htmlentities($host_id, ENT_QUOTES)."' AND host_macro_name LIKE '\$_HOST".htmlentities($macro_name, ENT_QUOTES)."\$'";
		$DBRESULT =& $this->DB->query($request); 
		return 0;	
	}
	 
	/*
	 * Set Poller link for an host 
	 */
	protected function setPoller($host_id, $poller_id) {
		if ($this->register == 0) {
			return ;
		}
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
	protected function unsetPoller($host_id) {
		if ($this->register == 0) {
			return ;
		}
		
		if (!isset($host_id)) {
			print "Bad parameters\n";
			exit(1);
		} else {
			$request = "DELETE FROM ns_host_relation WHERE host_host_id = '".$host_id."'";
			$this->DB->query($request);
			return 0;			
		}
	}
	
	/* *************************************
	 * Enable Disable Host
	 */
	public function enable($options) {
		
		$check = $this->checkParameters($options);
		if ($check) {
			return 1;
		}
	
		$host_id = $this->getHostID(htmlentities($options, ENT_QUOTES));
		if ($this->hostExists($options)) {
			$request = "UPDATE host SET host_activate = '1' WHERE host_id = '".$host_id."' AND host_register = '".$this->register."'";
			$this->DB->query($request);
			return 0;
		} else {
			print "Host '$options' doesn't exists.\n";
			return 1;
		}
	}
	
	public function disable($options) {
		
		$check = $this->checkParameters($options);
		if ($check) {
			return 1;
		}
	
		$host_id = $this->getHostID(htmlentities($options, ENT_QUOTES));
		if ($this->hostExists($options)) {
			$request = "UPDATE host SET host_activate = '0' WHERE host_id = '".$host_id."' AND host_register = '".$this->register."'";
			$this->DB->query($request);
			return 0;
		} else {
			print "Host '$options' doesn't exists.\n";
			return 1;
		}
	}	

	/* ***************************************
	 * Set ContactGroup link for notification
	 */
	public function setCG($options) {
		
		$check = $this->checkParameters($options);
		if ($check) {
			return 1;
		}
		$info = split(";", $options);
		
		$cg_id = $this->cg->getContactGroupID($info[1]);		
		
		/*
		 * Check contact ID
		 */
		if ($cg_id != 0) {

			$host_id = $this->getHostID($info[0]);		
			
			/*
			 * Clean all data 
			 */
			$request = "DELETE FROM contactgroup_host_relation WHERE contactgroup_cg_id = '$cg_id'  AND host_host_id = '$host_id'";
			$this->DB->query($request);
			
			/*
			 * Insert new entry
			 */
			$request = "INSERT INTO contactgroup_host_relation (contactgroup_cg_id, host_host_id) VALUES ('$cg_id', '$host_id')";
			$this->DB->query($request);
			return 0;			
		} else {
			print "Cannot find contact group : '".$info[1]."'.\n";
			return 1;
		}
	} 

	/* ***************************************
	 * UN-Set ContactGroup link for notification
	 */
	public function unsetCG($options) {
		$check = $this->checkParameters($options);
		if ($check) {
			return 1;
		}
		
		$info = split(";", $options);
		
		$cg_id = $this->cg->getContactGroupID($info[1]);		
		
		/*
		 * Check contact ID
		 */
		if ($cg_id != 0) {
			$host_id = $this->getHostID($info[0]);		
			
			/*
			 * Clean all data 
			 */
			$request = "DELETE FROM contactgroup_host_relation WHERE contactgroup_cg_id = '$cg_id'  AND host_host_id = '$host_id'";
			$this->DB->query($request);
			return 0;			
		} else {
			print "Cannot find contact group : '".$info[1]."'.\n";
			return 1;
		}
	}	 
}
?>