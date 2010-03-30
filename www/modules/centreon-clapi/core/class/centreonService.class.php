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
 
class CentreonService {
	
	var $DB;
	
	public function __construct($DB) {
		$this->DB = $DB;
	}
	
	public function testServiceExistence ($name = NULL, $host_id = NULL) {
		
		$name = str_replace('/', "#S#", $name);
		$name = str_replace('\\', "#BS#", $name);
		
		$DBRESULT =& $this->DB->query("SELECT service_id FROM service, host_service_relation hsr WHERE hsr.host_host_id = '".$host_id."' AND hsr.service_service_id = service_id AND service.service_description = '".htmlentities($name, ENT_QUOTES)."'");
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
	
	public function addService($information) {
		if (!isset($information["service_description"]) || !isset($information["host"]) || !isset($information["template"])) {
			return 0;
		} else {
			$information["service_description"] = str_replace("/", "#S#", $information["service_description"]);
			$information["service_description"] = str_replace("\\", "#BS#", $information["service_description"]);
			$request = "INSERT INTO service (service_description, service_template_model_stm_id, service_activate, service_register, service_active_checks_enabled, service_passive_checks_enabled, service_parallelize_check, service_obsess_over_service, service_check_freshness, service_event_handler_enabled, service_process_perf_data, service_retain_status_information, service_notifications_enabled, service_is_volatile) VALUES ('".$information["service_description"]."', '".$information["template"]."', '1', '1', '2', '2', '2', '2', '2', '2', '2', '2', '2', '2')";
			$this->DB->query($request);
			
			$request = "SELECT MAX(service_id) FROM service WHERE service_description = '".$information["service_description"]."' AND service_activate = '1' AND service_register = '1'";
			$DBRESULT =& $this->DB->query($request);
			$service = $DBRESULT->fetchRow();
			$service_id = $service["MAX(service_id)"];
			
			if ($service_id != 0 && $information["host"] != 0) {
				$request = "INSERT INTO host_service_relation (service_service_id, host_host_id) VALUES ('$service_id', '".$information["host"]."')";
				$this->DB->query($request);
				
				$request = "INSERT INTO extended_service_information (service_service_id) VALUE ('$service_id')";
				$this->DB->query($request);
				
				if (isset($information["macro"]))
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
			return $service_id;
		}
	}
}
 
?>