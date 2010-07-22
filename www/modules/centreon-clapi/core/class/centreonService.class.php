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
 
class CentreonService {
	
	var $DB;
	var $register;
	var $flag;
	var $object;
	
	var $host;
	var $contact;
	var $cg;
	
	var $parameters;
	var $paramTable;
	
	public function __construct($DB, $objName) {
		$this->DB = $DB;
		$this->register = 1;
		$this->object = $objName;

		if (strtoupper($objName) == "STPL") {
			$this->setTemplateFlag();
			$this->host = new CentreonHost($this->DB, "HTPL");
		} else {
			$this->host = new CentreonHost($this->DB, "HOST");
		}
		
		/*
		 * Create contact object
		 */
		require_once "./class/centreonCommand.class.php";
		require_once "./class/centreonContact.class.php";
		$this->contact = new CentreonContact($this->DB, "CONTACT");
		
		/*
		 * Create ContactGroup object
		 */
		require_once "./class/centreonContactGroup.class.php";
		$this->cg = new CentreonContactGroup($this->DB, "CG");
		
		/*
		 * Change buffers
		 */
		$this->setParametersList();
		$this->setParametersTable();
		$this->setFlags();
	}

	protected function setFlags() {
		$this->flag = array(0 => "No", 1 => "Yes", 2 => "Default");
	}

	protected function setParametersList() {
		$this->parameters = array();
		$this->parameters["description"] = "service_description";
		$this->parameters["alias"] = "service_alias";
		$this->parameters["template"] = "service_template_model_stm_id";
		
		$this->parameters["command"] = "command_command_id";
		$this->parameters["args"] = "command_command_id_arg";
		
		$this->parameters["max_check_attempts"] = "service_max_check_attempts";
		$this->parameters["normal_check_interval"] = "service_normal_check_interval";
		$this->parameters["retry_check_interval"] = "service_retry_check_interval";
		
		$this->parameters["active_checks_enabled"] = "service_active_checks_enabled";
		$this->parameters["passive_checks_enabled"] = "service_passive_checks_enabled";
		
		$this->parameters["notif_options"] = "service_notification_options";
		
		$this->parameters["check_period"] = "timeperiod_tp_id";
		$this->parameters["notif_period"] = "timeperiod_tp_id2";
		
		$this->parameters["url"] = "esi_notes_url";
	}
	
	protected function setParametersTable() {
		$this->paramTable = array();
		
		$this->paramTable["description"] = "service";
		$this->paramTable["alias"] = "service";
		$this->paramTable["template"] = "service";
		
		$this->paramTable["command"] = "service";
		$this->paramTable["args"] = "service";
		
		$this->paramTable["max_check_attempts"] = "service";
		$this->paramTable["normal_check_interval"] = "service";
		$this->paramTable["retry_check_interval"] = "service";
		
		$this->paramTable["active_checks_enabled"] = "service";
		$this->paramTable["passive_checks_enabled"] = "service";
		
		$this->paramTable["notif_options"] = "service";
		
		$this->paramTable["check_period"] = "service";
		$this->paramTable["notif_period"] = "service";
		
		$this->paramTable["url"] = "extended_service_information";
	}
	
	
	/* ************************
	 * Set object type : service or template
	 */
	protected function setTemplateFlag() {
		$this->register = 0;
	}
	
	protected function checkHostNumber($service_id) {
		$request = "SELECT host_host_id FROM host_service_relation WHERE service_service_id = '$service_id'";
		$DBRESULT =& $this->DB->query($request);
		if (isset($DBRESULT)) {
			$num = $DBRESULT->numRows();
			if (isset($num)) {
				return $num;
			}	
		}
		return -1;
	}
	
	protected function checkHostServiceRelation($host_id, $service_id) {
		$request = "SELECT host_host_id FROM host_service_relation WHERE service_service_id = '$service_id' AND host_host_id = '$host_id'";
		$DBRESULT =& $this->DB->query($request);
		if (isset($DBRESULT)) {
			$num = $DBRESULT->numRows();
			if (isset($num)) {
				return $num;
			}
		}
		return -1;
	}
	
	/* ************************************
	 * Check if service already exists.
	 */
	public function testServiceExistence ($name = NULL, $host_id = NULL) {
		
		$DBRESULT =& $this->DB->query("SELECT service_id FROM service, host_service_relation hsr WHERE hsr.host_host_id = '".$host_id."' AND hsr.service_service_id = service_id AND service.service_description LIKE '".htmlentities($this->encode($name), ENT_QUOTES)."'");
		$service =& $DBRESULT->fetchRow();
		if ($DBRESULT->numRows()) {
			$DBRESULT->free();
			return true;
		} else {
			return false;
		}
	}
	
	public function testServiceTplExistence ($name = NULL) {
		
		$DBRESULT =& $this->DB->query("SELECT service_id FROM service WHERE service.service_description LIKE '".htmlentities($this->encode($name), ENT_QUOTES)."'");
		$service =& $DBRESULT->fetchRow();
		if ($DBRESULT->numRows()) {
			$DBRESULT->free();
			return true;
		} else {
			return false;
		}
	}
	
	public function getServiceName($service_id, $readable = NULL) {
		$request = "SELECT service_description FROM service WHERE service_id = '$service_id'";
		$DBRESULT =& $this->DB->query($request);
		$data =& $DBRESULT->fetchRow();
		$DBRESULT->free();
		if (isset($data["service_description"]) && $data["service_description"]) {
			if (isset($readable) && $readable) {
				$data["service_description"] = $this->decode($data["service_description"]);
			}
			return $data["service_description"];
		} else {
			return "";
		}
	}
	
	public function getServiceAlias($service_id) {
		$request = "SELECT service_alias FROM service WHERE service_id = '$service_id'";
		$DBRESULT =& $this->DB->query($request);
		$data =& $DBRESULT->fetchRow();
		$DBRESULT->free();
		if (isset($data["service_alias"]) && $data["service_alias"])
			return $data["service_alias"];
		else
			return "";
	}
	
	public function hostTypeLink($service_id) {
		/*
		 * return 1 = host(s)
		 * return 2 = hostgroup(s)
		 * return 3 = host(s) + hostgroup(s) (Futur)
		 */
		$request = "SELECT host_host_id FROM host_service_relation WHERE hostgroup_hg_id IS NULL and service_service_id = '".(int)$service_id."'";
		$DBRESULT = $this->DB->query($request);
		if ($DBRESULT->numRows()) {
			return 1;
		} else {
			$request = "SELECT hostgroup_hg_id FROM host_service_relation WHERE hostgroup_hg_id IS NOT NULL and service_service_id = '".(int)$service_id."'";
			$DBRESULT = $this->DB->query($request);
			if ($DBRESULT->numRows()) {
				return 2;
			} else {
				return 0;
			}
		}
	}
	
	public function getServiceHosts($service_id) {
		
		$hostList = array();
		
		$request = "SELECT host_host_id FROM host_service_relation WHERE hostgroup_hg_id IS NULL and service_service_id = '".(int)$service_id."'";
		$DBRESULT = $this->DB->query($request);
		if ($DBRESULT->numRows()) {
			while ($h = $DBRESULT->fetchRow()) {
				$hostList[$h["host_host_id"]] = $h["host_host_id"];
			}
		}
		return $hostList;
	}
	
	public function getServiceHostGroups($service_id) {
		
		$hostGroupList = array();
		
		$request = "SELECT hostgroup_hg_id FROM host_service_relation WHERE hostgroup_hg_id IS NOT NULL and service_service_id = '".(int)$service_id."'";
		$DBRESULT = $this->DB->query($request);
		if ($DBRESULT->numRows()) {
			while ($h = $DBRESULT->fetchRow()) {
				$hostGroupList[$h["hostgroup_hg_id"]] = $h["hostgroup_hg_id"];
			}
		}
		return $hostGroupList;
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
	
	protected function checkParameters($options) {
		if (!isset($options) || $options == "") {
			print "No options defined. $str\n";
			$this->return_code = 1;
			return 1;
		}
	}
	
	/* ************************************
	 * Get service ID
	 */
	public function getServiceID($host_id, $service_description) {
		$DBRESULT =& $this->DB->query(	"SELECT service_id FROM service, host_service_relation hsr " .
										"WHERE hsr.host_host_id = '".$host_id."' AND hsr.service_service_id = service_id " .
										"AND service_description = '".$service_description."' LIMIT 1");
		$row =& $DBRESULT->fetchRow();
		if ($row["service_id"]) {
			return $row["service_id"];
		} else {
			return 0;
		}
	}
	
	/* ************************************
	 * Get service ID
	 */
	public function getServiceTplID($service_description) {
		$DBRESULT =& $this->DB->query(	"SELECT service_id FROM service " .
										"WHERE service_description = '".$service_description."' LIMIT 1");
		$row =& $DBRESULT->fetchRow();
		if ($row["service_id"]) {
			return $row["service_id"];
		} else {
			return 0;
		}
	}
	
	/* **********************************
	 *  Check if service is defind
	 */
	public function serviceExists($name = NULL, $host = NULL)	{

		$DBRESULT =& $this->DB->query(	"SELECT service_id " .
										"FROM service, host_service_relation hsr, host h " .
										"WHERE hsr.host_host_id = h.host_id " .
											"AND h.host_name LIKE '".$this->encode($host)."' " .
											"AND hsr.service_service_id = service_id " .
											"AND service.service_description = '".htmlentities($this->encode($name), ENT_QUOTES)."'");
		$service =& $DBRESULT->fetchRow();
		if ($DBRESULT->numRows() >= 1) {
			$DBRESULT->free();
			return true;
		}
		$DBRESULT->free();		
		return false;
	}
	
	/* **************************************
	 * Add services
	 */
	public function add($information) {

		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}
		
		$tabInfo = split(";", $information);
		
		if ($this->register && !$this->host->hostExists($tabInfo[0])) {
			print "Host doesn't exists.\n";
			return 1;
		}
		
		if ($this->serviceExists($tabInfo[1], $tabInfo[0])) {
			print "Service already exists.\n";
			return 1;
		}
		
		if ($this->register) {
			if (count($tabInfo) == 3) {
				$data = array("host" => $tabInfo[0], "service_description" => $tabInfo[1], "template" => $tabInfo[2]);
				return $this->addService($data);
			} else {
				print "No enought data for creating services.\n";
				return 1;
			}
		} else {
			if (count($tabInfo) == 3) {
				$data = array("service_description" => $tabInfo[0], "service_alias" => $tabInfo[1], "template" => $tabInfo[2]);
				return $this->addServiceTemplate($data);
			} else {
				print "No enought data for creating services template.\n";
				return 1;
			}
		}
	}
	
	protected function addServiceTemplate($information) {
		if (!isset($information["service_description"]) || !isset($information["template"])) {
			return 0;
		} else {
			if (preg_match("/^[0-9]*$/", $information["template"], $matches)) {
				$template = $information["template"];
			} else {
				$request = "SELECT service_id FROM service WHERE service_description LIKE '".$information["template"]."' LIMIT 1";
				$DBRESULT = $this->DB->query($request);
				$data = $DBRESULT->fetchRow();
				$template = $data["service_id"];
			}
			
			$request = "INSERT INTO service (service_description, service_alias, service_template_model_stm_id, service_activate, service_register, service_active_checks_enabled, service_passive_checks_enabled, service_parallelize_check, service_obsess_over_service, service_check_freshness, service_event_handler_enabled, service_process_perf_data, service_retain_status_information, service_notifications_enabled, service_is_volatile) VALUES ('".htmlentities($this->encode($information["service_description"]), ENT_QUOTES)."', '".htmlentities($this->encode($information["service_alias"]), ENT_QUOTES)."', '".$template."', '1', '".$this->register."', '2', '2', '2', '2', '2', '2', '2', '2', '2', '2')";
			$this->DB->query($request);
			
			$request = "SELECT MAX(service_id) FROM service WHERE service_description = '".htmlentities($this->encode($information["service_description"]), ENT_QUOTES)."' AND service_activate = '1' AND service_register = '".$this->register."'";
			$DBRESULT =& $this->DB->query($request);
			$service = $DBRESULT->fetchRow();
			$service_id = $service["MAX(service_id)"];
			
			if ($service_id != 0) {
				
				$request = "INSERT INTO extended_service_information (service_service_id) VALUE ('$service_id')";
				$this->DB->query($request);
				
				if (isset($information["macro"])) {
					foreach ($information["macro"] as $value) {
						if (strstr($value, ":")) {
							$tab = split(":", $value);
							if (isset($tab[1]) && $tab[1] != "") {
								$request = "INSERT INTO on_demand_macro_service (svc_macro_name, svc_macro_value, svc_svc_id) VALUE ('\$_SERVICE".$tab[0]."\$', '".$tab[1]."', '$service_id')";
								$this->DB->query($request);
							}
						}
					}
				}
			}
			return $service_id;	
		}
	}
	
	public function addService($information) {
		if (!isset($information["service_description"]) || !isset($information["host"]) || !isset($information["template"])) {
			return 0;
		} else {
			if (preg_match("/^[0-9]*$/", $information["template"], $matches)) {
				$template = $information["template"];
			} else {
				$request = "SELECT service_id FROM service WHERE service_description LIKE '".$information["template"]."' LIMIT 1";
				$DBRESULT = $this->DB->query($request);
				$data = $DBRESULT->fetchRow();
				$template = $data["service_id"];
			}
			
			if (preg_match("/^[0-9]*$/", $information["host"], $matches)) {
				$host = $information["host"];
			} else {
				$request = "SELECT host_id FROM host WHERE host_name LIKE '".$information["host"]."' LIMIT 1";
				$DBRESULT = $this->DB->query($request);
				$data = $DBRESULT->fetchRow();
				$host = $data["host_id"];
			}
			
			$request = "INSERT INTO service (service_description, service_template_model_stm_id, service_activate, service_register, service_active_checks_enabled, service_passive_checks_enabled, service_parallelize_check, service_obsess_over_service, service_check_freshness, service_event_handler_enabled, service_process_perf_data, service_retain_status_information, service_notifications_enabled, service_is_volatile) VALUES ('".htmlentities($this->encode($information["service_description"]), ENT_QUOTES)."', '".$template."', '1', '".$this->register."', '2', '2', '2', '2', '2', '2', '2', '2', '2', '2')";
			$this->DB->query($request);
			
			$request = "SELECT MAX(service_id) FROM service WHERE service_description = '".htmlentities($this->encode($information["service_description"]), ENT_QUOTES)."' AND service_activate = '1' AND service_register = '".$this->register."'";
			$DBRESULT =& $this->DB->query($request);
			$service = $DBRESULT->fetchRow();
			$service_id = $service["MAX(service_id)"];
			
			if ($service_id != 0 && $host != 0) {
				$request = "INSERT INTO host_service_relation (service_service_id, host_host_id) VALUES ('$service_id', '".$host."')";
				$this->DB->query($request);
				
				$request = "INSERT INTO extended_service_information (service_service_id) VALUE ('$service_id')";
				$this->DB->query($request);
				
				if (isset($information["macro"])) {
					foreach ($information["macro"] as $value) {
						if (strstr($value, ":")) {
							$tab = split(":", $value);
							if (isset($tab[1]) && $tab[1] != "") {
								$request = "INSERT INTO on_demand_macro_service (svc_macro_name, svc_macro_value, svc_svc_id) VALUE ('\$_SERVICE".$tab[0]."\$', '".$tab[1]."', '$service_id')";
								$this->DB->query($request);
							}
						}
					}
				}
			}
			return $service_id;
		}
	}
	
	/* ***************************************
	 * Show all services
	 */
	public function show($search_string = NULL) {
		
		if ($this->register) {
			
			$search = "";
			if ($search_string != "") {
				$search = " AND (service_description LIKE '%$search_string%' OR service_alias LIKE '%$search_string%') ";
			}
			
			$request = "SELECT service_id, service_description, service_alias, s.command_command_id, s.timeperiod_tp_id, service_max_check_attempts, service_normal_check_interval, service_retry_check_interval,service_active_checks_enabled, service_passive_checks_enabled, s.command_command_id_arg, host_id, host_name FROM service s, host h, host_service_relation hr WHERE s.service_id = hr.service_service_id AND hr.host_host_id = h.host_id AND service_register = '".$this->register."' AND host_register = '1' $search ORDER BY host_name, service_description";
			$DBRESULT = $this->DB->query($request);
			$i = 0;
			while ($data = $DBRESULT->fetchRow()) {
				if ($i == 0) {
					print "hostid;svcid;host;description;command;args;checkPeriod;maxAttempts;checkInterval;retryInterval;active;passive\n";
				}
				$i++;
				print $data["host_id"].";".$data["service_id"].";".$this->decode($data["host_name"]).";".html_entity_decode($this->decode($data["service_description"]), ENT_QUOTES).";".html_entity_decode($this->decode($data["command_name"]), ENT_QUOTES).";".html_entity_decode($this->decode($data["command_command_id_arg"]), ENT_QUOTES).";".$this->decode($data["timeperiod_tp_id"]).";".$data["service_max_check_attempts"].";".$data["service_normal_check_interval"].";".$data["service_retry_check_interval"].$this->flag[$data["service_active_checks_enabled"]].";".$this->flag[$data["service_passive_checks_enabled"]]."\n";
			}
			$DBRESULT->free();
		} else {
			
			$search = "";
			if ($search_string != "") {
				$search = " AND (service_description LIKE '%$search_string%' OR service_alias LIKE '%$search_string%') ";
			}
			
			$request = "SELECT service_id, service_description, service_alias, s.command_command_id, s.timeperiod_tp_id, service_max_check_attempts, service_normal_check_interval, service_retry_check_interval,service_active_checks_enabled, service_passive_checks_enabled, s.command_command_id_arg FROM service s WHERE service_register = '".$this->register."' $search ORDER BY service_description, service_alias";
			$DBRESULT = $this->DB->query($request);
			$i = 0;
			while ($data = $DBRESULT->fetchRow()) {
				if ($i == 0) {
					print "svcid;name;service_name;command;args;checkPeriod;maxAttempts;checkInterval;retryInterval;active;passive\n";
				}
				$i++;
				print $data["service_id"].";".html_entity_decode($this->decode($data["service_description"]), ENT_QUOTES).";".html_entity_decode($this->decode($data["service_alias"]), ENT_QUOTES).";".html_entity_decode($this->decode($data["command_name"]), ENT_QUOTES).";".html_entity_decode($this->decode($data["command_command_id_arg"]), ENT_QUOTES).";".$this->decode($data["timeperiod_tp_id"]).";".$data["service_max_check_attempts"].";".$data["service_normal_check_interval"].";".$data["service_retry_check_interval"].$this->flag[$data["service_active_checks_enabled"]].";".$this->flag[$data["service_passive_checks_enabled"]]."\n";
			}
			$DBRESULT->free();
		}
		
	}
	
	/* ***************************************
	 * Delete a service
	 */
	public function del($information) {
		
		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}
		
		$tabInfo = split(";", $information);
		
		if (!$this->host->hostExists($tabInfo[0])) {
			print "Host doesn't exists.\n";
			return 1;
		}
		
		if (!$this->serviceExists($tabInfo[1], $tabInfo[0])) {
			print "Service doesn't exists.\n";
			return 1;
		} else {
			
			/*
			 * Looking for service id 
			 */
			$request = "SELECT service_service_id as service_id FROM host_service_relation hr, host h, service s WHERE s.service_description LIKE '".$tabInfo[1]."' AND hr.service_service_id = s.service_id AND h.host_id = hr.host_host_id AND h.host_name LIKE '".$tabInfo[0]."' AND h.host_register = '".$this->register."'";
			$DBRESULT = $this->DB->query($request);
			$data =& $DBRESULT->fetchRow();
			$service_id = $data["service_id"];
			$DBRESULT->free();
			
			/*
			 * Delete service
			 */
			$request = "DELETE FROM service WHERE service_id = '".$service_id."' ";
			$this->DB->query($request);
			return 0;
		} 
	}
	
	/* *******************************************
	 * Set Macro 
	 */
	public function setMacro($informations) {
		
		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}
		
		$info = split(";", $informations);
		if (count($info) == 4) {
			$return_code = $this->setMacroService($info[0], $info[1], $info[2], $info[3]);
		} else {
			print "Not enought arguments.\n";
			$return_code = 1;
		}
		return $return_code;
	}

	protected function setMacroService($host_name, $service_description, $macro_name, $macro_value) {
		if ((!isset($host_name) || !isset($macro_name)) && (!isset($service_description) || !isset($service_description))) {
			print "Bad parameters\n";
			return 1;
		}
		
		$macro_name = strtoupper($macro_name);
		
		$host_id = $this->host->getHostID(htmlentities($host_name, ENT_QUOTES));
		$service_id = $this->getServiceID($host_id, $service_description);
		
		if ($service_id != 0) {
			$request = "SELECT COUNT(*) FROM on_demand_macro_service WHERE svc_svc_id = '".htmlentities($service_id, ENT_QUOTES)."' AND svc_macro_name LIKE '\$_SERVICE".htmlentities($macro_name, ENT_QUOTES)."\$'";
			$DBRESULT =& $this->DB->query($request); 
			$data =& $DBRESULT->fetchRow();
			if ($data["COUNT(*)"]) {
				$request = "UPDATE on_demand_macro_service SET svc_macro_value = '".htmlentities($macro_value, ENT_QUOTES)."' WHERE svc_svc_id = '".htmlentities($service_id, ENT_QUOTES)."' AND svc_macro_name LIKE '\$_SERVICE".htmlentities($macro_name, ENT_QUOTES)."\$' LIMIT 1";
				$DBRESULT =& $this->DB->query($request);
				return 0;
			} else {
				$request = "INSERT INTO on_demand_macro_service (svc_svc_id, svc_macro_value, svc_macro_name) VALUES ('".htmlentities($service_id, ENT_QUOTES)."', '".htmlentities($macro_value, ENT_QUOTES)."', '\$_SERVICE".htmlentities($macro_name, ENT_QUOTES)."\$')";
				$DBRESULT =& $this->DB->query($request);
				return 0;
			}			
		} else {
			print "Cannot find service ID.\n";
			return 1;
		}
	}

	/* *******************************************
	 * Un-Set Macro 
	 */
	public function delMacro($informations) {
		
		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}
		
		$info = split(";", $informations);
		$return_code = $this->delMacroService($info[0], $info[1], $info[2]);
		return $return_code;
	}
	
	protected function delMacroService($host_name, $service_description, $macro_name) {
		if ((!isset($host_name) || !isset($macro_name)) && (!isset($service_description) || !isset($service_description))) {
			print "Bad parameters\n";
			return 1;
		}
		
		$macro_name = strtoupper($macro_name);

		$host_id = $this->host->getHostID(htmlentities($host_name, ENT_QUOTES));
		$service_id = $this->getServiceID($host_id, $service_description);
		
		$request = "DELETE FROM on_demand_macro_service WHERE svc_svc_id = '".htmlentities($service_id, ENT_QUOTES)."' AND svc_macro_name LIKE '\$_SERVICE".htmlentities($macro_name, ENT_QUOTES)."\$'";
		$DBRESULT =& $this->DB->query($request); 
		return 0;	
	}

	/* ******************************************
	 * Set parameters
	 */
	public function setParam($informations) {
		
		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}
		
		$info = split(";", $informations);
		$return_code = $this->setParamService($info[0], $info[1], $info[2], $info[3]);
		return $return_code;
	}

	protected function setParamService($host_name, $service_description, $param, $value) {
		if (isset($this->parameters[$param]) && isset($this->paramTable[$param])) {
			if ($this->register) {
				$host_id = $this->host->getHostID(htmlentities($host_name, ENT_QUOTES));
				
				if (!$this->serviceExists($service_description, $host_id)) {
					print "Unknown service.\n";
					return 1; 
				}
				
				if ($param == "template") {
					$value = $cmd->getServiceTplID($value);
				}
				
				if ($param == "command") {
					require_once "./class/centreonCommand.class.php";
					$cmd = new CentreonCommand($this->DB);
					$value = $cmd->getCommandID($value);
				}
				
				if ($param == "check_period" || $param == "notif_period") {
					require_once "./class/centreonTimePeriod.class.php";
					$tp = new CentreonTimePeriod($this->DB);
					$value = $tp->getTimeperiodId($value);
				}
				
				$service_id = $this->getServiceID($host_id, $service_description);
				$request = "UPDATE ".$this->paramTable[$param]." SET ".$this->parameters[$param]." = '".$value."' WHERE ".($this->paramTable[$param] == "service" ? "" : "service_")."service_id = '$service_id'";
				$this->DB->query($request);
				return 0;
			} else {
				if (!$this->testServiceTplExistence($service_description)) {
					print "Unknown service template.\n";
					return 1; 
				}
				
				if ($param == "template") {
					$value = $cmd->getServiceTplID($value);
				}
				
				if ($param == "command") {
					require_once "./class/centreonCommand.class.php";
					$cmd = new CentreonCommand($this->DB);
					$value = $cmd->getCommandID($value);
				}
				
				if ($param == "check_period" || $param == "notif_period") {
					require_once "./class/centreonTimePeriod.class.php";
					$tp = new CentreonTimePeriod($this->DB);
					$value = $tp->getTimeperiodId($value);
				}
				
				$service_id = $this->getServiceTplID($service_description);
				$request = "UPDATE ".$this->paramTable[$param]." SET ".$this->parameters[$param]." = '".$value."' WHERE ".($this->paramTable[$param] == "service" ? "" : "service_")."service_id = '$service_id'";
				$this->DB->query($request);
			}
		} else {
			print "Unknown parameters for a service.\n";
			return 1;
		}
		return 0;
	}
	
	/* ***************************************
	 * Set Contact lionk for notification
	 */
	public function setContact($options) {
		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}
		
		$info = split(";", $options);
		if ($this->register) {
			$contact_id = $this->contact->getContactID($info[2]);
		} else {
			$contact_id = $this->contact->getContactID($info[1]);
		}
	
		/*
		 * Check contact IS
		 */	
		if ($contact_id != 0) {
			if ($this->register) {
				$host_id = $this->host->getHostID($info[0]);		
				$service_id = $this->getServiceID($host_id, $info[1]);
			} else {
				$service_id = $this->getServiceTplID($info[0]);
			}
			
			/*
			 * Clean all data 
			 */
			$request = "DELETE FROM contact_service_relation WHERE contact_id = '$contact_id'  AND service_service_id = '$service_id'";
			$this->DB->query($request);
			
			/*
			 * Insert new entry
			 */
			$request = "INSERT INTO contact_service_relation (contact_id, service_service_id) VALUES ('$contact_id', '$service_id')";
			$this->DB->query($request);
			return 0;			
		} else {
			print "Cannot find user : '".$info[2]."'.\n";
			return 1;
		}
	} 

	/* ***************************************
	 * UN-Set Contact lionk for notification
	 */
	public function unsetContact($options) {
		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}
		
		$info = split(";", $options);
		
		require_once "./class/centreonContact.class.php";
		$contact = new CentreonContact($this->DB, "CONTACT");
		
		$contact_id = $contact->getContactID($info[2]);
		$host_id = $this->host->getHostID($info[0]);		
		$service_id = $this->getServiceID($host_id, $info[1]);
		
		/*
		 * Clean all data 
		 */
		$request = "DELETE FROM contact_service_relation WHERE contact_id = '$contact_id'  AND service_service_id = '$service_id'";
		$this->DB->query($request);
		
		return 0;
	} 

	/* ***************************************
	 * Set ContactGroup lionk for notification
	 */
	public function setCG($options) {
		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}
		
		$info = split(";", $options);
		if ($this->register) {
			$cg_id = $this->cg->getContactGroupID($info[2]);	
		} else {
			$cg_id = $this->cg->getContactGroupID($info[1]);		
		}
		
		/*
		 * Check contact ID
		 */
		if ($cg_id != 0) {
			if ($this->register) {
				$host_id = $this->host->getHostID($info[0]);		
				$service_id = $this->getServiceID($host_id, $info[1]);				
			} else {
				$service_id = $this->getServiceTplID($info[0]);	
			}
			
			/*
			 * Clean all data 
			 */
			$request = "DELETE FROM contactgroup_service_relation WHERE contactgroup_cg_id = '$cg_id'  AND service_service_id = '$service_id'";
			$this->DB->query($request);
			
			/*
			 * Insert new entry
			 */
			$request = "INSERT INTO contactgroup_service_relation (contactgroup_cg_id, service_service_id) VALUES ('$cg_id', '$service_id')";
			$this->DB->query($request);
			return 0;			
		} else {
			print "Cannot find contact group : '".$info[2]."'.\n";
			return 1;
		}
	} 

	/* ***************************************
	 * UN-Set ContactGroup lionk for notification
	 */
	public function unsetCG($options) {
		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}
		
		$info = split(";", $options);
		if ($this->register) {
			$cg_id = $this->cg->getContactGroupID($info[2]);	
		} else {
			$cg_id = $this->cg->getContactGroupID($info[1]);		
		}
		
		/*
		 * Check contact ID
		 */
		if ($cg_id != 0) {
			if ($this->register) {
				$host_id = $this->host->getHostID($info[0]);		
				$service_id = $this->getServiceID($host_id, $info[1]);				
			} else {
				$service_id = $this->getServiceTplID($info[0]);	
			}
			
			/*
			 * Clean all data 
			 */
			$request = "DELETE FROM contactgroup_service_relation WHERE contactgroup_cg_id = '$cg_id'  AND service_service_id = '$service_id'";
			$this->DB->query($request);
			return 0;			
		} else {
			print "Cannot find contact group : '".$info[2]."'.\n";
			return 1;
		}
	} 
	
	/* *********************************************
	 * Set Hopst Link
	 */
	public function setHost($options) {
		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}
		
		$info = split(";", $options);
		
		if ($this->register) {
			$host_id = $this->host->getHostID($info[0]);
			$service_id = $this->getServiceID($host_id, $info[1]);	
			
			/*
			 * Get host link
			 */
			$host_link_id = $this->host->getHostID($info[2]);
			
			/*
			 * Delete all data
			 */
			$request = "DELETE FROM host_service_relation WHERE service_service_id = '".$service_id."' AND host_host_id = '".$host_link_id."'";
			$this->DB->query($request);

			/*
			 * Insert new entry
			 */
			$request = "INSERT INTO host_service_relation (host_host_id, service_service_id) VALUES ('".$host_link_id."', '".$service_id."')";
			$this->DB->query($request);
		} else {
			$service_id = $this->getServiceTplID($info[0]);	
			
			/*
			 * Get host link
			 */
			$host_link_id = $this->host->getHostID($info[1]);
			
			/*
			 * Delete all data
			 */
			$request = "DELETE FROM host_service_relation WHERE service_service_id = '".$service_id."' AND host_host_id = '".$host_link_id."'";
			$this->DB->query($request);

			/*
			 * Insert new entry
			 */
			$request = "INSERT INTO host_service_relation (host_host_id, service_service_id) VALUES ('".$host_link_id."', '".$service_id."')";
			$this->DB->query($request);
		}
		return 0;
	}
	
	/* *********************************************
	 * Set Hopst Link
	 */
	public function unsetHost($options) {
		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}
		
		$info = split(";", $options);
		
		if ($this->register) {
			$host_id = $this->host->getHostID($info[0]);
			$service_id = $this->getServiceID($host_id, $info[1]);	
			
			if ($service_id == 0) {
				print "Couple host/service not Found.\n";
				return 1;
			}
			
			$hostNumber = $this->checkHostNumber($service_id);
			if ($hostNumber == 1) {
				print "Cannot remove this host link for service '".$info[1]."' because only this host is actually attached to this service.\n";
				return 1;
			} else if ($hostNumber == -1) {
				print "Unknown error.\n";
				return 1;
			} 

			/*
			 * Get host link
			 */
			$host_link_id = $this->host->getHostID($info[2]);
			
			/*
			 * Delete all data
			 */
			$request = "DELETE FROM host_service_relation WHERE service_service_id = '".$service_id."' AND host_host_id = '".$host_link_id."'";
			$this->DB->query($request);
		} else {
			$service_id = $this->getServiceTplID($info[0]);	
			
			/*
			 * Get host link
			 */
			$host_link_id = $this->host->getHostID($info[1]);
			
			/*
			 * Delete all data
			 */
			$request = "DELETE FROM host_service_relation WHERE service_service_id = '".$service_id."' AND host_host_id = '".$host_link_id."'";
			$this->DB->query($request);
		}
		return 0;
	}
	
}
?>