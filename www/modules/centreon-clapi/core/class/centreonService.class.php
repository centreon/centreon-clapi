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
	
	public function __construct($DB, $objName) {
		$this->DB = $DB;
		$this->register = 1;
		$this->object = $objName;

		if (strtoupper($objName) == "STPL") {
			$this->setTemplateFlag();
		}
		
		$this->flag = array(0 => "No", 1 => "Yes", 2 => "Default");
	}
	
	protected function setTemplateFlag() {
		$this->register = 0;
	}
	
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
	
	public function getServiceName($service_id) {
		$request = "SELECT service_description FROM service WHERE service_id = '$service_id'";
		$DBRESULT =& $this->DB->query($request);
		$data =& $DBRESULT->fetchRow();
		$DBRESULT->free();
		if (isset($data["service_description"]) && $data["service_description"])
			return $data["service_description"];
		else
			return "";
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
	
	private function encode($str) {
		$str = str_replace("/", "#S#", $str);
		$str = str_replace("\\", "#BS#", $str);
		return $str;			
	}
	
	private function decode($str) {
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

		$this->checkParameters($information);
		
		$tabInfo = split(";", $information);
		
		$host = new CentreonHost($this->DB, "HOST");
		
		if (!$host->hostExists($tabInfo[1])) {
			print "Host doesn't exists.\n";
			return 1;
		}
		
		if ($this->serviceExists($tabInfo[0], $tabInfo[1])) {
			print "Service already exists.\n";
			return 1;
		}
		
		if (count($tabInfo) == 3) {
			$data = array("host" => $tabInfo[0], "service_description" => $tabInfo[1], "template" => $tabInfo[2]);
			return $this->addService($data);
		} else {
			print "No enought data for creating services.\n";
			return 1;
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
			
			$request = "INSERT INTO service (service_description, service_template_model_stm_id, service_activate, service_register, service_active_checks_enabled, service_passive_checks_enabled, service_parallelize_check, service_obsess_over_service, service_check_freshness, service_event_handler_enabled, service_process_perf_data, service_retain_status_information, service_notifications_enabled, service_is_volatile) VALUES ('".htmlentities($this->encode($information["service_description"]), ENT_QUOTES)."', '".$template."', '1', '1', '2', '2', '2', '2', '2', '2', '2', '2', '2', '2')";
			$this->DB->query($request);
			
			$request = "SELECT MAX(service_id) FROM service WHERE service_description = '".htmlentities($this->encode($information["service_description"]), ENT_QUOTES)."' AND service_activate = '1' AND service_register = '1'";
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
						print $value . '\n';
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
			$request = "SELECT service_id, service_description, service_alias, s.command_command_id, command_name, s.timeperiod_tp_id, service_max_check_attempts, service_normal_check_interval, service_retry_check_interval,service_active_checks_enabled, service_passive_checks_enabled, s.command_command_id_arg, host_id, host_name FROM service s, host h, host_service_relation hr, command cmd WHERE cmd.command_id = s.command_command_id AND s.service_id = hr.service_service_id AND hr.host_host_id = h.host_id AND service_register = '".$this->register."' AND host_register = '1' ORDER BY host_name, service_description";
			$DBRESULT = $this->DB->query($request);
			$i = 0;
			while ($data = $DBRESULT->fetchRow()) {
				if ($i == 0) {
					print "hostid;svcid;host;description;command;args;checkPeriod;maxAttempts;checkInterval;retryInterval;active;passive;";
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
			
			$request = "SELECT service_id, service_description, service_alias, s.command_command_id, command_name, s.timeperiod_tp_id, service_max_check_attempts, service_normal_check_interval, service_retry_check_interval,service_active_checks_enabled, service_passive_checks_enabled, s.command_command_id_arg FROM service s, command cmd WHERE cmd.command_id = s.command_command_id AND service_register = '".$this->register."' $search ORDER BY service_description, service_alias";
			$DBRESULT = $this->DB->query($request);
			$i = 0;
			while ($data = $DBRESULT->fetchRow()) {
				if ($i == 0) {
					print "svcid;name;service_name;command;args;checkPeriod;maxAttempts;checkInterval;retryInterval;active;passive;";
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
		
		$this->checkParameters($information);
		
		$tabInfo = split(";", $information);
		
		$host = new CentreonHost($this->DB, $this->object);
		
		if (!$host->hostExists($tabInfo[0])) {
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
		
		$this->checkParameters($informations);
		
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
		
		$host = new CentreonHost($this->DB, "HOST");
		
		$host_id = $host->getHostID(htmlentities($host_name, ENT_QUOTES));
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
		
		$this->checkParameters($informations);
		
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

		$host = new CentreonHost($this->DB, "HOST");
		$host_id = $host->getHostID(htmlentities($host_name, ENT_QUOTES));
		$service_id = $this->getServiceID($host_id, $service_description);
		
		$request = "DELETE FROM on_demand_macro_service WHERE svc_svc_id = '".htmlentities($service_id, ENT_QUOTES)."' AND svc_macro_name LIKE '\$_SERVICE".htmlentities($macro_name, ENT_QUOTES)."\$'";
		$DBRESULT =& $this->DB->query($request); 
		return 0;	
	}

}
 
?>