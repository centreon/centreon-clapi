<?php
/**
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

/**
 *
 * Centreon Host objects
 * @author jmathis
 *
 */
class CentreonHost {
	private $DB;
	private $host_name;
	private $host_id;
	private $register;
	private $cg;
	private $cct;
	private $hg;
	private $_timeperiod;
	private $_cmd;
	public $obj;

	private $access;

	/**
	 *
	 * Object Constructor
	 * @param unknown_type $DB
	 * @param unknown_type $objName
	 */
	public function __construct($DB, $objName) {
		$this->DB = $DB;
		$this->register = 1;

		/***
		 * Enable Access Object
		 */
		$this->access = new CentreonACLResources($this->DB);

		if (strtoupper($objName) == "HTPL") {
			$this->setTemplateFlag();
		}

		/**
		 * Create ContactGroup object
		 */
		require_once "./class/centreonContactGroup.class.php";
		$this->cg = new CentreonContactGroup($this->DB, "CG");

		/*
		 * Create Contact object
		 */
	    require_once "./class/centreonCommand.class.php";
	    require_once "./class/centreonContact.class.php";
    	$this->cct = new CentreonContact($this->DB, "CONTACT");

		$this->obj = strtoupper($objName);

		$this->_timeperiod = new CentreonTimePeriod($this->DB);
		$this->_cmd = new CentreonCommand($this->DB);
	}

	/**
	 *
	 * Get Version of Centreon
	 */
	protected function getVersion() {
		$request = "SELECT * FROM informations";
		$DBRESULT = $this->DB->query($request);
		$info = $DBRESULT->fetchRow();
		return $info["value"];
	}

	/**
	 *
	 * Set var in order to known if object is a template or not.
	 */
	protected function setTemplateFlag() {
		$this->register = 0;
	}

	/**
	 *
	 * Check host existance
	 * @param unknown_type $name
	 */
	public function hostExists($name) {
		if (!isset($name)) {
			return 0;
		}

		/**
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

	/**
	 *
	 * check if host template exists
	 * @param unknown_type $name
	 */
	public function _hostTemplateExists($name) {
		if (!isset($name)) {
			return 0;
		}

		/**
		 * Get informations
		 */
		$DBRESULT =& $this->DB->query("SELECT host_name, host_id FROM host WHERE host_name = '".htmlentities($name, ENT_QUOTES)."' AND host_register = '0'");
		if ($DBRESULT->numRows() >= 1) {
			$host =& $DBRESULT->fetchRow();
			$DBRESULT->free();
			return $host["host_id"];
		} else {
			return 0;
		}
	}

	/**
	 *
	 * Check parameters
	 * if no options, return errors
	 *
	 * @param unknown_type $options
	 */
	protected function checkParameters($options) {
		if (!isset($options) || $options == "") {
			print "No options defined.\n";
			return 1;
		}
	}

	/**
	 *
	 * Validate that all names are not using forbidden characters
	 * @param unknown_type $name
	 */
	protected function validateName($name) {
		if (preg_match('/^[0-9a-zA-Z\_\-\ \/\\\.]*$/', $name, $matches) && strlen($name)) {
			return $this->checkNameformat($name);
		} else {
			print "Name '$name' doesn't match with Centreon naming rules.\n";
			exit (1);
		}
	}

	/**
	 *
	 * Verifie the lenght of the host name
	 * @param unknown_type $name
	 */
	protected function checkNameformat($name) {
		if (strlen($name) > 40) {
			print "Warning: host name reduce to 40 caracters.\n";
		}
		return sprintf("%.40s", $name);
	}

	/**
	 *
	 * Get Poller id
	 * @param $name
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

	/**
	 *
	 * Get id of host
	 * @param unknown_type $name
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

	/**
	 *
	 * Get Name of an host
	 * @param $host_id
	 * @param $readable
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

	/**
	 *
	 * Encode String
	 * @param $str
	 */
	protected function encode($str) {
		global $version;

		if (!strncmp($version, "2.1", 3)) {
			$str = str_replace("/", "#S#", $str);
			$str = str_replace("\\", "#BS#", $str);
		}
		return $str;
	}

	/**
	 *
	 * Decode String
	 * @param $str
	 */
	protected function decode($str) {
		global $version;

		if (!strncmp($version, "2.1", 3)) {
			$str = str_replace("#S#", "/", $str);
			$str = str_replace("#BS#", "\\", $str);
		}
		return $str;
	}


	/** ***********************************
	 * Add functions
	 */

	/**
	 *
	 * Add functions
	 * @param unknown_type $options
	 */
	public function add($options) {

		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}

		$svc = new CentreonService($this->DB, "Service");
		$info = split(";", $options);

		/**
		 * Check host_name / host_alias rules
		 */
		$info[0] = $this->validateName($info[0]);

		if (!$this->hostExists($info[0]) && strlen($info[0])) {
			if ($this->register) {
				$convertionTable = array(0 => "host_name", 1 => "host_alias", 2 => "host_address", 3 => "host_template", 4 => "host_poller", 5 => "hostgroup");
				$informations = array();
				foreach ($info as $key => $value) {
					$informations[$convertionTable[$key]] = $value;
				}
				$host_id = $this->addHost($informations);
				$this->deployServiceTemplates($host_id, $svc);
				if ($host_id) {
					return 0;
				} else {
					return $host_id;
				}
			} else {
				$convertionTable = array(0 => "host_name", 1 => "host_alias", 2 => "host_address", 3 => "host_template");
				$informations = array();
				foreach ($info as $key => $value) {
					$informations[$convertionTable[$key]] = $value;
				}
				$host_id = $this->addHostTemplate($informations);
				if ($host_id) {
					return 0;
				} else {
					return $host_id;
				}
			}
		} else {
			if ($this->register) {
				$type = "";
			} else {
				$type = " template";
			}

			print "Host$type ".$info[0]." already exists.\n";
			return 1;
		}
	}

	/** *************************************************
	 * Add an host
	 */

	/**
	 *
	 * Add Host function
	 * @param unknown_type $information
	 */
	protected function addHost($information) {
		if (!isset($information["host_name"]) || !isset($information["host_address"]) || !isset($information["host_poller"])) {
			if (!isset($information["host_name"])) {
				print "ERROR: Name is a mandatory parameter.\n";
			}
			if (!isset($information["host_address"])) {
				print "ERROR: Address is a mandatory parameter.\n";
			}
			if (!isset($information["host_poller"])) {
				print "ERROR: poller is a mandatory parameter.\n";
			}
			return 0;
		} else {
			if (!isset($information["host_alias"]) || $information["host_alias"] == "") {
				$information["host_alias"] = $information["host_name"];
			}

			/***
			 * Init HostGroup object
			 */
			$this->hg = new CentreonHostGroup($this->DB);

			/***
			 * check host template existance
			 */
			if ($information["host_template"]) {
				if (strstr($information["host_template"], ",")) {
					$tab = split(",", $information["host_template"]);
					foreach ($tab as $hostTemplate) {
						if (!$this->_hostTemplateExists($hostTemplate)) {
							print "Template '$hostTemplate' does not exists.\n";
							return 2;
						}
					}
				} else {
					if (!$this->_hostTemplateExists($information["host_template"])) {
						print "Template '".$information["host_template"]."' does not exists.\n";
						return 2;
					}
				}
			}

			/***
			 * Check if hostgroup(s) exists
			 */
			if (isset($information["hostgroup"]) && $information["hostgroup"]) {
				if (strstr($information["hostgroup"], ",")) {
					$tab = split(",", $information["hostgroup"]);
					foreach ($tab as $hostgroup_name) {
						if (!$this->hg->hostGroupExists($hostgroup_name)) {
							print "Hostgroup '$hostgroup_name' does not exists.\n";
							return 2;
						}
					}
				} else {
					if (!$this->hg->hostGroupExists($information["hostgroup"])) {
						print "Hostgroup '".$information["hostgroup"]."' does not exists.\n";
						return 2;
					}
				}
			}

			/***
			 * Insert Host
			 */
			$request = 	"INSERT INTO host (host_name, host_alias, host_address, host_register, host_activate, host_active_checks_enabled, host_passive_checks_enabled, host_checks_enabled, host_obsess_over_host, host_check_freshness, host_event_handler_enabled, host_flap_detection_enabled, host_process_perf_data, host_retain_status_information, host_retain_nonstatus_information, host_notifications_enabled) " .
						"VALUES ('".htmlentities(trim($this->encode($information["host_name"])), ENT_QUOTES)."', '".htmlentities(trim($this->encode($information["host_alias"])), ENT_QUOTES)."', '".htmlentities(trim($information["host_address"]), ENT_QUOTES)."', '".$this->register."', '1', '2', '2', '2', '2', '2', '2', '2', '2', '2', '2', '2')";
			$this->DB->query($request);

			/***
			 * Get host ID.
			 */
			$host_id = $this->getHostID(htmlentities($information["host_name"], ENT_QUOTES));

			/***
			 * Insert Template Relation
			 */
			if ($information["host_template"]) {
				$count = 1;
				if (strstr($information["host_template"], ",")) {
					$tab = split(",", $information["host_template"]);
					foreach ($tab as $hostTemplate) {
						$request = "INSERT INTO host_template_relation (host_tpl_id, host_host_id, `order`) VALUES ((SELECT host_id FROM host WHERE host_name LIKE '".$hostTemplate."'), '".$host_id."', '$count')";
						$this->DB->query($request);
						$count++;
					}
				} else {
					$request = "INSERT INTO host_template_relation (host_tpl_id, host_host_id, `order`) VALUES ((SELECT host_id FROM host WHERE host_name LIKE '".$information["host_template"]."'), '".$host_id."', '$count')";
					$this->DB->query($request);
				}
				unset($count);
			}

			/***
			 * Insert hostgroup relation
			 */
			if (isset($information["hostgroup"]) && $information["hostgroup"]) {
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

			/***
			 * Insert Extended Info
			 */
			$request = "INSERT INTO extended_host_information (host_host_id) VALUES ('".$host_id."')";
			$this->DB->query($request);

			/***
			 * Insert Host Poller
			 */
			$this->setPoller($host_id, $this->getPollerID($information["host_poller"]));

			/**
			 * Update ACL
			 */
			$this->access->updateACL();

			return $host_id;
		}
	}

	/**
	 * Add an host template
	 */
	protected function addHostTemplate($information) {
		if (!isset($information["host_name"])) {
			if (!isset($information["host_name"])) {
				print "ERROR: Template Name is a mandatory parameter.\n";
			}
			return 0;
		} else {
			if (!isset($information["host_alias"]) || $information["host_alias"] == "") {
				$information["host_alias"] = $information["host_name"];
			}

			/***
			 * check host template existance
			 */
			if ($information["host_template"]) {
				if (strstr($information["host_template"], ",")) {
					$tab = split(",", $information["host_template"]);
					foreach ($tab as $hostTemplate) {
						if (!$this->_hostTemplateExists($hostTemplate)) {
							print "Template '$hostTemplate' does not exists.\n";
							return 2;
						}
					}
				} else {
					if (!$this->_hostTemplateExists($information["host_template"])) {
						print "Template '".$information["host_template"]."' does not exists.\n";
						return 2;
					}
				}
			}

			/**
			 * Insert Host
			 */
			$request = 	"INSERT INTO host (host_name, host_alias, host_address, host_register, host_activate, host_active_checks_enabled, host_passive_checks_enabled, host_checks_enabled, host_obsess_over_host, host_check_freshness, host_event_handler_enabled, host_flap_detection_enabled, host_process_perf_data, host_retain_status_information, host_retain_nonstatus_information, host_notifications_enabled) " .
						"VALUES ('".htmlentities(trim($this->encode($information["host_name"])), ENT_QUOTES)."', '".htmlentities(trim($this->encode($information["host_alias"])), ENT_QUOTES)."', '".htmlentities(trim($information["host_address"]), ENT_QUOTES)."', '".$this->register."', '1', '2', '2', '2', '2', '2', '2', '2', '2', '2', '2', '2')";
			$this->DB->query($request);

			/**
			 * Get host ID.
			 */
			$host_id = $this->getHostID(htmlentities($information["host_name"], ENT_QUOTES));

			/**
			 * Insert Template Relation
			 */
			if ($information["host_template"]) {
				$order = 1;
				if (strstr($information["host_template"], ",")) {
					$tab = split(",", $information["host_template"]);
					foreach ($tab as $hostTemplate) {
						$request = "INSERT INTO host_template_relation (host_tpl_id, host_host_id, `order`) VALUES ((SELECT host_id FROM host WHERE host_name LIKE '".$hostTemplate."'), '".$host_id."', '$order')";
						$this->DB->query($request);
						$order++;
					}
				} else {
					$request = "INSERT INTO host_template_relation (host_tpl_id, host_host_id, `order`) VALUES ((SELECT host_id FROM host WHERE host_name LIKE '".$information["host_template"]."'), '".$host_id."', '$order')";
					$this->DB->query($request);
				}
				unset($order);
			}

			/**
			 * Insert Extended Info
			 */
			$request = "INSERT INTO extended_host_information (host_host_id) VALUES ('".$host_id."')";
			$this->DB->query($request);

			return $host_id;
		}
	}

	/**
	 * Apply Template
	 */
	public function applyTPL($options) {

		$this->checkParameters($options);

		/**
		 * Create service class
		 */
		$svc = new CentreonService($this->DB, "Service");

		$host_id = $this->getHostID($options);
		$this->deployServiceTemplates($host_id, $svc);

		/**
		 * Update ACL
		 */
		$this->access->updateACL();

		return 1;
	}

	/** *************************************
	 * Delete Host
	 */
	public function del($options) {

		$this->checkParameters($options);

		/*
		 * Get Host
		 */
		$request = "SELECT host_id FROM host WHERE host_name = '".htmlentities($this->decode($options), ENT_QUOTES)."' LIMIT 1";
		$DBRESULT = $this->DB->query($request);
		$host_key = $DBRESULT->fetchRow();
		$rq = "SELECT @nbr := (SELECT COUNT( * ) FROM host_service_relation WHERE service_service_id = hsr.service_service_id GROUP BY service_service_id) AS nbr, hsr.service_service_id FROM host_service_relation hsr, host WHERE hsr.host_host_id = '".$host_key["host_id"]."' AND host.host_id = hsr.host_host_id AND host.host_register = '1'";
		$DBRESULT2 = $this->DB->query($rq);
		while ($row = $DBRESULT2->fetchRow()) {
			if ($row["nbr"] == 1) {
				$DBRESULT3 = $this->DB->query("SELECT service_description FROM service WHERE service_id = '".$row["service_service_id"]."' LIMIT 1");
				$svcname = $DBRESULT3->fetchRow();
				$DBRESULT4 = $this->DB->query("DELETE FROM service WHERE service_id = '".$row["service_service_id"]."'");
			}
		}
		$DBRESULT = $this->DB->query("DELETE FROM host WHERE host_id = '".$host_key["host_id"]."'");
		$DBRESULT = $this->DB->query("DELETE FROM host_template_relation WHERE host_host_id = '".$host_key["host_id"]."'");
		$DBRESULT = $this->DB->query("DELETE FROM on_demand_macro_host WHERE host_host_id = '".$host_key["host_id"]."'");
		$DBRESULT = $this->DB->query("DELETE FROM contact_host_relation WHERE host_host_id = '".$host_key["host_id"]."'");
		$this->return_code = 0;
		return;
	}

	/** *******************************************
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

	/** ******************************************
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
				/**
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
		return 1;
	}

	/** ***********************************************
	 * List all hosts or templates
	 */
	public function show($host_name = NULL) {
		$search = "";
		if (isset($host_name)) {
			$search = " AND (host_name like '%".htmlentities($host_name, ENT_QUOTES)."%' OR host_alias LIKE '%".htmlentities($host_name, ENT_QUOTES)."%') ";
		}

		if ($this->register == 1) {
			$request = "SELECT host_id, host_address, host_name, host_alias, ns.name AS poller FROM host, nagios_server ns , ns_host_relation nhr WHERE host.host_id = nhr.host_host_id AND nhr.nagios_server_id AND ns.id = nhr.nagios_server_id AND host_register = '".$this->register."' $search ORDER BY host_name";
			$DBRESULT =& $this->DB->query($request);
			$i = 0;
			while ($data =& $DBRESULT->fetchRow()) {
				if ($i == 0) {
					print "id;name;alias;address;poller;templates;hostgroups\n";
				}
				print $this->decode($data["host_id"]).";".$this->decode($data["host_name"]).";".$data["host_alias"].";".$data["host_address"].($this->register ? ";".$data["poller"].";".$this->getTemplateList($data["host_id"]).";".$this->getHostGroupList($data["host_id"]) : "")."\n";
				$i++;
			}
			$DBRESULT->free();
		} else {
			$request = "SELECT host_id, host_address, host_name, host_alias FROM host WHERE host_register = '".$this->register."' $search ORDER BY host_name";
			$DBRESULT =& $this->DB->query($request);
			$i = 0;
			while ($data =& $DBRESULT->fetchRow()) {
				if ($i == 0) {
					print "id;name;alias;address;templates\n";
				}
				print $this->decode($data["host_id"]).";".$this->decode($data["host_name"]).";".$data["host_alias"].";".$data["host_address"].";".$this->getTemplateList($data["host_id"])."\n";
				$i++;
			}
			$DBRESULT->free();
		}
		unset($data);
	}

	/** ***********************************************
	 * export all hosts or templates
	 */

	/**
	 *
	 * export all hosts or templates
	 */
	public function export() {
        if ($this->register == 1) {
            $request = "SELECT host_id, host_address, host_name, host_alias, ns.name AS poller FROM host, nagios_server ns , ns_host_relation nhr WHERE host.host_id = nhr.host_host_id AND nhr.nagios_server_id AND ns.id = nhr.nagios_server_id AND host_register = '".$this->register."' ORDER BY host_name";
            $DBRESULT =& $this->DB->query($request);
            while ($data =& $DBRESULT->fetchRow()) {
                print $this->obj.";ADD;".$this->decode($data["host_name"]).";".$data["host_alias"].";".$data["host_address"].";".$this->getTemplateList($data["host_id"]).";".$data["poller"].";".$this->getHostGroupList($data["host_id"])."\n";
                $this->exportParents($data["host_id"]);
                $this->exportMacros($data["host_id"]);
                $this->exportNotes($data["host_id"]);
                $this->exportProperties($data["host_id"]);
                $this->exportContactGroup($data["host_id"]);
            }
            $DBRESULT->free();
        } else {
            $request = "SELECT host_id, host_address, host_name, host_alias FROM host WHERE host_register = '".$this->register."' ORDER BY host_name";
            $DBRESULT =& $this->DB->query($request);
            while ($data =& $DBRESULT->fetchRow()) {
                print $this->obj.";ADD;".$this->decode($data["host_name"]).";".$data["host_alias"].";".$data["host_address"].";".$this->getTemplateList($data["host_id"])."\n";
                $this->exportMacros($data["host_id"]);
                $this->exportProperties($data["host_id"]);
                $this->exportContactGroup($data["host_id"]);
            }
            $DBRESULT->free();
        }
    }

	/**
	 *
	 * Export parent hosts list
	 * @param unknown_type $host_id
	 */
	private function exportParents($host_id) {
        $str = "";
        $request = "SELECT host_name FROM host, host_hostparent_relation WHERE host_id = host_parent_hp_id AND host_host_id = '".$host_id."'";
        $DBRESULT =& $this->DB->query($request);
        while ($data = $DBRESULT->fetchRow()) {
        	if ($str != "") {
                $str .= ",";
            }
            $str .= $data["host_name"];
        }
        $DBRESULT->free();
        if ($str != "") {
        	print $this->obj.";SETPARENT;" . $this->getHostName($host_id) . ";" . $str . "\n";
        }
    }

    /**
     *
     * Export macro of host and templates
     * @param $host_id
     */
	private function exportMacros($host_id) {
        $request = "SELECT host_macro_name, host_macro_value FROM on_demand_macro_host WHERE host_host_id = '$host_id'";
        $DBRESULT =& $this->DB->query($request);
        while ($data =& $DBRESULT->fetchRow()) {
        	print $this->obj.";SETMACRO;" . $this->getHostName($host_id) . ";".$data["host_macro_name"].";".$data["host_macro_value"]."\n";
        }
        $DBRESULT->free();
    }

    /**
     *
     * Export all generic properties of an host
     * @param $host_id
     */
    private function exportProperties($host_id) {
		$this->exportHostProperties($host_id, "check_interval");
		if (strncmp($this->getVersion(), "2.1", 3)) {
			$this->exportHostProperties($host_id, "retry_check_interval");
		}
		$this->exportHostProperties($host_id, "max_check_attempts");
		$this->exportURL($host_id);
		$this->exportNote($host_id);
		$this->exportURLAction($host_id);
		$this->exportSNMPCommunity($host_id);
		$this->exportSNMPVersion($host_id);
		$this->exportTP($host_id, "");
		$this->exportTP($host_id, 2);
		$this->exportCMD($host_id);
		$this->exportCMDArgs($host_id);
		$this->exportHostProperties($host_id, "notification_interval");
		$this->exportHostProperties($host_id, "notification_options");
    	$this->exportHostProperties($host_id, "notifications_enabled");
    	$this->exportHostProperties($host_id, "active_checks_enabled");
    	$this->exportHostProperties($host_id, "passive_checks_enabled");
    }

    /**
     *
     * Export host properties stored in host table
     * @param $host_id
     * @param $properties
     */
	private function exportHostProperties($host_id, $properties) {
		$request = "SELECT host_".$properties." FROM `host` WHERE host_id = '$host_id'";
		$DBRESULT =& $this->DB->query($request);
 		while ($data =& $DBRESULT->fetchRow()) {
 			if (isset($data["host_".$properties]) && $data["host_".$properties] != "") {
 				print $this->obj.";SETPARAM;" . $this->getHostName($host_id) . ";$properties;".$data["host_".$properties]."\n";
 			}
 		}
 		$DBRESULT->free();
    }

    /**
     *
     * Export URL
     * @param $host_id
     */
    private function exportURL($host_id) {
		$request = "SELECT ehi_notes_url FROM `extended_host_information` WHERE host_host_id = '$host_id'";
		$DBRESULT =& $this->DB->query($request);
 		while ($data =& $DBRESULT->fetchRow()) {
 			if (isset($data["ehi_notes_url"]) && $data["ehi_notes_url"] != "") {
 				print $this->obj.";SETPARAM;" . $this->getHostName($host_id) . ";url;".$data["ehi_notes_url"]."\n";
 			}
 		}
 		$DBRESULT->free();
    }

 	/**
     *
     * Export Note
     * @param $host_id
     */
    private function exportNote($host_id) {
		$request = "SELECT ehi_notes FROM `extended_host_information` WHERE host_host_id = '$host_id'";
		$DBRESULT =& $this->DB->query($request);
 		while ($data =& $DBRESULT->fetchRow()) {
 			if (isset($data["ehi_notes"]) && $data["ehi_notes"] != "") {
 				print $this->obj.";SETPARAM;" . $this->getHostName($host_id) . ";notes;".$data["ehi_notes"]."\n";
 			}
 		}
 		$DBRESULT->free();
    }

    /**
     *
     * Export Notes
     * @param $host_id
     */
	private function exportNotes($host_id) {
		$request = "SELECT ehi_notes FROM `extended_host_information` WHERE host_host_id = '$host_id'";
		$DBRESULT =& $this->DB->query($request);
 		while ($data =& $DBRESULT->fetchRow()) {
 			if (isset($data["ehi_notes"]) && $data["ehi_notes"] != "") {
 				print $this->obj.";SETPARAM;" . $this->getHostName($host_id) . ";note;".$data["ehi_notes"]."\n";
 			}
 		}
 		$DBRESULT->free();
    }

    /**
     *
     * Export Action URL
     * @param $host_id
     */
    private function exportURLAction($host_id) {
		$request = "SELECT ehi_action_url FROM `extended_host_information` WHERE host_host_id = '$host_id'";
		$DBRESULT =& $this->DB->query($request);
 		while ($data =& $DBRESULT->fetchRow()) {
        	if (isset($data["ehi_action_url"]) && $data["ehi_action_url"] != "") {
 				print $this->obj.";SETPARAM;" . $this->getHostName($host_id) . ";actionurl;".$data["ehi_action_url"]."\n";
        	}
 		}
 		$DBRESULT->free();
    }

    /**
     *
     * Export SNMP community
     * @param $host_id
     */
    private function exportSNMPCommunity($host_id) {
		$request = "SELECT host_snmp_community FROM `host` WHERE host_id = '$host_id'";
		$DBRESULT =& $this->DB->query($request);
 		while ($data =& $DBRESULT->fetchRow()) {
 			if (isset($data["host_snmp_community"]) && $data["host_snmp_community"] != "") {
 				print $this->obj.";SETPARAM;" . $this->getHostName($host_id) . ";community;".$data["host_snmp_community"]."\n";
 			}
 		}
 		$DBRESULT->free();
    }

    /**
     *
     * Export SNMP version
     * @param $host_id
     */
    private function exportSNMPVersion($host_id) {
		$request = "SELECT host_snmp_version FROM `host` WHERE host_id = '$host_id'";
		$DBRESULT =& $this->DB->query($request);
 		while ($data =& $DBRESULT->fetchRow()) {
 			if (isset($data["host_snmp_version"]) && $data["host_snmp_version"] != 0) {
 				print $this->obj.";SETPARAM;" . $this->getHostName($host_id) . ";version;".$data["host_snmp_version"]."\n";
 			}
 		}
 		$DBRESULT->free();
    }

    /**
     *
     * Export Host timeperiod
     * @param $host_id
     * @param $type
     */
    private function exportTP($host_id, $type) {
		$request = "SELECT timeperiod_tp_id$type FROM `host` WHERE host_id = '$host_id'";
		$DBRESULT =& $this->DB->query($request);
 		while ($data =& $DBRESULT->fetchRow()) {
 			if (isset($data["timeperiod_tp_id$type"]) && $data["timeperiod_tp_id$type"] != 0) {
 				print $this->obj.";SETPARAM;" . $this->getHostName($host_id) . ";".($type == '' ? "tpcheck" : "notifcheck").";".$this->_timeperiod->getTimeperiodName($data["timeperiod_tp_id$type"])."\n";
 			}
 		}
 		$DBRESULT->free();
    }

    /**
     *
     * Export Host command
     * @param $host_id
     */
	private function exportCMD($host_id) {
		$request = "SELECT command_command_id FROM `host` WHERE host_id = '$host_id'";
		$DBRESULT =& $this->DB->query($request);
 		while ($data =& $DBRESULT->fetchRow()) {
 			if (isset($data["command_command_id"]) && $data["command_command_id"] != 0) {
 				print $this->obj.";SETPARAM;" . $this->getHostName($host_id) . ";check_command;".$this->_cmd->getCommandName($data["command_command_id"])."\n";
 			}
 		}
 		$DBRESULT->free();
    }

    /**
     *
     * Export Host commands Args
     * @param unknown_type $host_id
     */
	private function exportCMDArgs($host_id) {
		$request = "SELECT command_command_id_arg1 FROM `host` WHERE host_id = '$host_id'";
		$DBRESULT =& $this->DB->query($request);
 		while ($data =& $DBRESULT->fetchRow()) {
			print $this->obj.";SETPARAM;" . $this->getHostName($host_id) . ";check_command_args;".$data["command_command_id_arg1"]."\n";
 		}
 		$DBRESULT->free();
    }

    /**
     *
     * Export contactgroup of an host
     * @param unknown_type $host_id
     */
    private function exportContactGroup($host_id) {
		$request = "SELECT cg_name FROM contactgroup_host_relation, contactgroup WHERE host_host_id = '$host_id' AND cg_id = contactgroup_cg_id";
		$DBRESULT =& $this->DB->query($request);
 		while ($data =& $DBRESULT->fetchRow()) {
			print $this->obj.";SETCG;" . $this->getHostName($host_id) . ";".$data["cg_name"]."\n";
 		}
 		$DBRESULT->free();
    }


	/** **********************************************
	 * Get the list of all hostgroup for one host
	 */
	private function getHostGroupList($host_id) {
		$request = "SELECT hg_name FROM hostgroup, hostgroup_relation hr WHERE hr.host_host_id = '$host_id' AND hostgroup.hg_id = hr.hostgroup_hg_id";
		$DBRESULT =& $this->DB->query($request);
		$list = '';
		while ($data =& $DBRESULT->fetchRow()) {
			if ($list != '') {
				$list .= ',';
			}
			$list .= $data["hg_name"];
		}
		return $list;
	}

	/** **********************************************
	 * Get the list of all templates for one host
	 */
	private function getTemplateList($host_id) {
    	$request = "SELECT host_name FROM host, host_template_relation htr WHERE htr.host_host_id = '$host_id' AND host.host_id = htr.host_tpl_id";
    	$DBRESULT =& $this->DB->query($request);
        $list = '';
        while ($data =& $DBRESULT->fetchRow()) {
        	if ($list != '') {
            	$list .= ',';
            }
            $list .= $data["host_name"];
   		}
        return $list;
   }

	/** *********************************************
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
			/**
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

			/**
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

			/**
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
			/**
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

	/**
	 *
	 * Intro function in order to manage parameter for an host
	 * @param $options
	 */
	public function setParam($options) {

		$this->checkParameters($options);

		$elem = split(";", $options);
		$exitcode = $this->setParameterHost($elem[0], $elem[1], $elem[2]);
		return $exitcode;
	}


	/**
	 *
	 * Set Parameters
	 * @param $host_name
	 * @param $parameter
	 * @param $value
	 */
	protected function setParameterHost($host_name, $parameter, $value) {
		/**
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
			"notifcheck" => "host",
			"check_command" => "host",
			"check_command_args" => "host",
			"max_check_attempts" => "host",
			"check_interval" => "host",
			"retry_check_interval" => "host",
			"notification_interval" => "host",
			"notification_options" => "host",
			"notifications_enabled" => "host",
			"active_checks_enabled" => "host",
			"passive_checks_enabled" => "host",
			"url" => "extended_host_information",
			"notes" => "extended_host_information",
			"actionurl" => "extended_host_information",
		);

		/**
		 * Set Real field name
		 */
		$realNameField = array(
			"name" => "host_name",
			"alias" => "host_alias",
			"address" => "host_address",
			"community" => "host_snmp_community",
			"version" => "host_snmp_version",
			"tpcheck" => "timeperiod_tp_id",
			"notifcheck" => "timeperiod_tp_id2",
			"check_command" => "command_command_id",
			"check_command_args" => "command_command_id_arg1",
			"max_check_attempts" => "host_max_check_attempts",
			"check_interval" => "host_check_interval",
			"retry_check_interval" => "host_retry_check_interval",
			"notification_interval" => "host_notification_interval",
			"notification_options" => "host_notification_options",
			"notifications_enabled" => "host_notifications_enabled",
			"active_checks_enabled" => "host_active_checks_enabled",
			"passive_checks_enabled" => "host_passive_checks_enabled",
			"url" => "ehi_notes_url",
			"notes" => "ehi_notes",
			"actionurl" => "ehi_action_url",
		);

		/**
		 * Host or host_extentended info
		 */
		$host_id_field = array("host" => "host_id", "extended_host_information" => "host_host_id");
		if (!isset($tabName[$parameter])) {
			print "Unknown parameter '$parameter' for host.\n";
			return 1;
		}

		/**
		 * Check timeperiod case
		 */
		if ($parameter == "tpcheck" || $parameter == "notifcheck") {
			$request = "SELECT tp_id FROM timeperiod WHERE tp_name LIKE '".htmlentities($value, ENT_QUOTES)."'";
			$DBRESULT =& $this->DB->query($request);
			$data = $DBRESULT->fetchRow();
			$value = $data["tp_id"];
		}

		/**
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

		/**
		 * Check command case
		 */
		if ($parameter == "check_command") {
			$request = "SELECT command_id FROM command WHERE command_name LIKE '".htmlentities($value, ENT_QUOTES)."'";
			$DBRESULT =& $this->DB->query($request);
			$data = $DBRESULT->fetchRow();
			$value = $data["command_id"];
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
			return 1;
		} else {
			print "Unknown host : $host_name.\n";
			return 1;
		}
	}

	/**
	 *
	 * Add a host template link for an host
	 * @param unknown_type $information
	 */
	public function addTemplate($information) {
		$check = $this->checkParameters($information);
		if ($check) {
			return 1;
		}

		$elem = split(";", $information);

		if (!$this->hostExists($elem[0])) {
			print "Host".$type." '".$elem[0]."' does not exists.\n";
			return 1;
		}

		if (!$this->_hostTemplateExists($elem[1])) {
			print "Host template '".$elem[1]."' does not exists.\n";
			return 1;
		}

		$exitcode = $this->addTemplateHost($elem[0], $elem[1]);
		return $exitcode;
	}

	/**
	 *
	 * Add a host template link for an host in database
	 * @param unknown_type $host_name
	 * @param unknown_type $template
	 * @param unknown_type $order
	 */
	protected function addTemplateHost($host_name, $template, $order = null) {
		if (isset($host_name) && $host_name != "" && isset($template) && $template != "") {

			if (!isset($order)) {
				$request = "SELECT MAX(`order`) FROM host, host_template_relation WHERE host.host_name LIKE '$host_name' AND host_template_relation.host_host_id = host.host_id";
				$DBRESULT = $this->DB->query($request);
				$info = $DBRESULT->fetchRow();
				$order = (int)$info["MAX(order)"];
				unset($info);
				unset($DBRESULT);
			} else {
				$order = (int)$order;
			}

			$svc = new CentreonService($this->DB, "Service");

			$request = "SELECT * FROM host_template_relation " .
						"WHERE host_host_id = (SELECT host_id FROM host WHERE host_name LIKE '".$host_name."') " .
								"AND host_tpl_id = (SELECT host_id FROM host WHERE host_name LIKE '".$template."')";
			$DBRESULT = $this->DB->query($request);
			if ($DBRESULT->numRows() == 0) {
				/**
				 * Get Host ID
				 */
				$host_id = $this->getHostID($host_name);

				$request = "INSERT INTO host_template_relation (host_tpl_id, host_host_id, `order`) VALUES ((SELECT host_id FROM host WHERE host_name LIKE '".$template."'), '".$host_id."', '$order')";
				$this->DB->query($request);
				if ($this->register) {
					$this->deployServiceTemplates($host_id, $svc);
				}

				/**
				 * Update ACL
				 */
				$this->access->updateACL();

			} else {
				print "Template already added.\n";
				return 1;
			}
		} else {
			print "Check parameters.\n";
			return 1;
		}
	}

	/**
	 *
	 * Delete a host template link for an host
	 * @param $information
	 */
	public function delTemplate($information) {
		$svc = new CentreonService($this->DB, "Service");

		$check = $this->checkParameters($information);
		if ($check) {
			return 1;
		}

		$elem = split(";", $information);
		if (!$this->hostExists($elem[0])) {
			print "Host".$type." '".$elem[0]."' does not exists.\n";
			return 1;
		}

		if (!$this->_hostTemplateExists($elem[1])) {
			print "Host template '".$elem[1]."' does not exists.\n";
			return 1;
		}

		$elem = split(";", $information);
		$exitcode = $this->delTemplateHost($elem[0], $elem[1]);
		return $exitcode;
	}

	/**
	 *
	 * Delete a host template link for an host in database
	 * @param unknown_type $host_name
	 * @param unknown_type $template
	 */
	protected function delTemplateHost($host_name, $template) {
		if (isset($host_name) && $host_name != "" && isset($template) && $template != "") {

			$svc = new CentreonService($this->DB, "Service");

			$request = "SELECT * FROM host_template_relation " .
						"WHERE host_host_id = (SELECT host_id FROM host WHERE host_name LIKE '".$host_name."') " .
								"AND host_tpl_id = (SELECT host_id FROM host WHERE host_name LIKE '".$template."')";
			$DBRESULT = $this->DB->query($request);
			if ($DBRESULT->numRows() == 1) {

				/**
				 * Get Host ID
				 */
				$host_id = $this->getHostID($host_name);

				$request = "DELETE FROM host_template_relation WHERE host_tpl_id IN (SELECT host_id FROM host WHERE host_name LIKE '".$template."') AND host_host_id = '".$host_id."'";
				$this->DB->query($request);

				/**
				 * Update ACL
				 */
				$this->access->updateACL();

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

	/**
	 *
	 * Set host macro
	 * @param $host_name
	 * @param $macro_name
	 * @param $macro_value
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

	/**
	 *
	 * Delete host macro
	 * @param $host_name
	 * @param $macro_name
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

	/**
	 *
	 * Set Poller link for an host
	 * @param $host_id
	 * @param $poller_id
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

	/**
	 *
	 * Free Poller link
	 * @param $host_id
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

	/**
	 *
	 * Enable Disable Host
	 * @param unknown_type $options
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

	/**
	 *
	 * Disable host
	 * @param unknown_type $options
	 */
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

	/**
	 *
	 * Set ContactGroup link for notification
	 * @param $options
	 */
	public function setCG($options) {

		$check = $this->checkParameters($options);
		if ($check) {
			return 1;
		}
		$info = split(";", $options);

		$cg_id = $this->cg->getContactGroupID($info[1]);

		/**
		 * Check contact ID
		 */
		if ($cg_id != 0) {

			$host_id = $this->getHostID($info[0]);

			/**
			 * Clean all data
			 */
			$request = "DELETE FROM contactgroup_host_relation WHERE contactgroup_cg_id = '$cg_id'  AND host_host_id = '$host_id'";
			$this->DB->query($request);

			/**
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

	/**
	 *
	 * UN-Set ContactGroup link for notification
	 * @param $options
	 */
	public function unsetCG($options) {
		$check = $this->checkParameters($options);
		if ($check) {
			return 1;
		}

		$info = split(";", $options);

		$cg_id = $this->cg->getContactGroupID($info[1]);

		/**
		 * Check contact ID
		 */
		if ($cg_id != 0) {
			$host_id = $this->getHostID($info[0]);

			/**
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

    /**
     *
     * Set Contact link for notification
     * @param unknown_type $options
     */
	public function setContact($options) {

 		$check = $this->checkParameters($options);
 		if ($check) {
 			return 1;
 		}
 		$info = split(";", $options);

 		$contact_id = $this->cct->getContactID($info[1]);

 		/**
 		 * Check contact ID
 		 */
 		if ($contact_id != 0) {

 			$host_id = $this->getHostID($info[0]);

 			/**
 			 * Clean all data
 			 */
 			$request = "DELETE FROM contact_host_relation WHERE contact_id = '$contact_id'  AND host_host_id = '$host_id'";
 			$this->DB->query($request);

 			/**
 			 * Insert new entry
 			 */
 			$request = "INSERT INTO contact_host_relation (contact_id, host_host_id) VALUES ('$contact_id', '$host_id')";
 			$this->DB->query($request);
 			return 0;
 		} else {
 			print "Cannot find user : '".$info[1]."'.\n";
 			return 1;
 		}
 	}

  	/**
  	 *
  	 * UN-Set Contact link for notification
  	 * @param $options
  	 */
 	public function unsetContact($options) {

 	    $check = $this->checkParameters($options);
 		if ($check) {
 			return 1;
 		}

 		$info = split(";", $options);

 		$contact_id = $this->cct->getContactID($info[1]);

 		/**
 		 * Check contact ID
 		 */
 		if ($contact_id != 0) {
 			$host_id = $this->getHostID($info[0]);

 			/**
 			 * Clean all data
 			 */
 			$request = "DELETE FROM contact_host_relation WHERE contact_id = '$contact_id'  AND host_host_id = '$host_id'";
 			$this->DB->query($request);
 			return 0;
 		} else {
 			print "Cannot find user : '".$info[1]."'.\n";
 			return 1;
 		}
 	}

}
?>
