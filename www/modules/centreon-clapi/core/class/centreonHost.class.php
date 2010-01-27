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

	public function deployServiceTemplates($host_id, $objService, $tpl_id = NULL) {
		if (!isset($tpl_id))
			$tpl_id = $host_id;
		if (isset($tpl_id) && $tpl_id) {
			$request = "SELECT host_tpl_id FROM host_template_relation WHERE host_host_id = '$tpl_id'";
			$DBRESULT =& $this->DB->query($request);
			while ($data =& $DBRESULT->fetchRow()) {
				print $this->getHostName($data["host_tpl_id"]) . "\n";
				/*
				 * Check if service is linked
				 */
				$request2 = "SELECT service_service_id FROM host_service_relation WHERE host_host_id = '".$data["host_tpl_id"]."'";
				$DBRESULT2 =& $this->DB->query($request2);
				while ($svc =& $DBRESULT2->fetchRow()) {
					$name = $objService->getServiceName($svc["service_service_id"]);
					if (!$objService->testServiceExistence($name, $host_id)) {
						$objService->addService(array("service_description" => $name, "template" => $svc["service_service_id"], "host" => $host_id, "macro" => array()));
						print "TPL : ".$svc["service_service_id"]." = ".$name."\n";
					}
				}
				$DBRESULT2->free();
				$this->deployServiceTemplates($host_id, $objService, $data["host_tpl_id"]);
			}
			$DBRESULT->free();
		}
	}

	public function addHost($information) {
		if (!isset($information["host_name"]) || !isset($information["host_address"]) || !isset($information["host_template"]) || !isset($information["host_poller"])) {
			return 0;
		} else {
			if (!isset($information["host_alias"]) || $information["host_alias"] == "")
				$information["host_alias"] = $information["host_name"];
			/*
			 * Insert Host
			 */
			$request = 	"INSERT INTO host (host_name, host_alias, host_address, host_register, host_activate) " .
						"VALUES ('".$information["host_name"]."', '".$information["host_alias"]."', '".$information["host_address"]."', '1', '1')";
			$this->DB->query($request);
			$host_id = $this->getHostID($information["host_name"]);
			
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
			$request = "INSERT INTO ns_host_relation (nagios_server_id, host_host_id) VALUES ('".$information["host_poller"]."', '".$host_id."')";
			$this->DB->query($request);
			return $host_id;
		}
	}
	
	public function getHostID($name) {
		$request = "SELECT host_id FROM host WHERE host_name = '".trim($name)."' AND host_register = '1'";
		$DBRESULT =& $this->DB->query($request);
		if ($DBRESULT->numRows()) {
			$info =& $DBRESULT->fetchRow();
			$DBRESULT->free();
			return $info["host_id"];
		} else {
			return 0;
		}
	}	
	
}
 
?>