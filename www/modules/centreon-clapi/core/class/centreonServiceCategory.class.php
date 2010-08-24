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

class CentreonServiceCategory {
	private $DB;
	private $access;

	public function __construct($DB) {
		$this->DB = $DB;

		/**
		 * Enable Access Object
		 */
		$this->access = new CentreonACLResources($this->DB);

	}

	/*
	 * Check host existance
	 */
	protected function serviceCategoryExists($name) {
		if (!isset($name))
			return 0;

		/*
		 * Get informations
		 */
		$DBRESULT =& $this->DB->query("SELECT sc_name, sc_id FROM service_categories WHERE sc_name = '".htmlentities($name, ENT_QUOTES)."'");
		if ($DBRESULT->numRows() >= 1) {
			$sc =& $DBRESULT->fetchRow();
			$DBRESULT->free();
			return $sc["sc_id"];
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

	public function getServiceCategoryID($sc_name = NULL) {
		if (!isset($sc_name))
			return;

		$request = "SELECT sc_id FROM service_categories WHERE sc_name LIKE '$sc_name'";
		$DBRESULT =& $this->DB->query($request);
		$data =& $DBRESULT->fetchRow();
		return $data["sc_id"];
	}

	protected function validateName($name) {
		if (preg_match('/^[0-9a-zA-Z\_\-\ \/\\\.]*$/', $name, $matches) && strlen($name)) {
			return $this->checkNameformat($name);
		} else {
			print "Name '$name' doesn't match with Centreon naming rules.\n";
			exit (1);
		}
	}

	protected function checkNameformat($name) {
		if (strlen($name) > 40) {
			print "Warning: host name reduce to 40 caracters.\n";
		}
		return sprintf("%.40s", $name);
	}

	/* ****************************************
	 *  Delete Action
	 */

	public function del($name) {
		$request = "DELETE FROM service_categories WHERE sc_name LIKE '".htmlentities($name, ENT_QUOTES)."'";
		$DBRESULT =& $this->DB->query($request);

		/**
		 * Update ACL
		 */
		$this->access->updateACL();

		return 0;
	}

	/* ****************************************
	 * Dislay all SG
	 */
	public function show($search = NULL) {
		/*
		 *  * Set Search
		 */
		$searchStr = "";
		if (isset($search) && $search != "") {
			$searchStr = " WHERE sc_name LIKE '%".htmlentities($search, ENT_QUOTES)."%' OR sc_description LIKE '%".htmlentities($search, ENT_QUOTES)."%'";
		}

		/*
		 * Get Child informations
		 */
		require_once "./class/centreonHost.class.php";
		require_once "./class/centreonService.class.php";
		$host = new CentreonHost($this->DB, "HOST");
		$svc = new CentreonService($this->DB, "SERVICE");

		$request = "SELECT sc_id, sc_name, sc_description FROM service_categories $searchStr ORDER BY sc_name";
		$DBRESULT =& $this->DB->query($request);
		if (isset($DBRESULT) && $DBRESULT->numRows()) {
			$i = 0;
			while ($data =& $DBRESULT->fetchRow()) {
				if ($i == 0) {
					print "Name;Alias;Members\n";
				}
				print html_entity_decode($data["sc_name"], ENT_QUOTES).";".html_entity_decode($data["sc_description"], ENT_QUOTES).";";

				/*
				 * Get Childs informations
				 */
				$request = "SELECT service_service_id FROM service_categories_relation WHERE sc_id = '".$data["sc_id"]."'";
				$DBRESULT2 =& $this->DB->query($request);
				$i2 = 0;
				while ($m =& $DBRESULT2->fetchRow()) {
					$type = $svc->hostTypeLink($m["service_service_id"]);
					if ($type == 1) {
						$hostList = $svc->getServiceHosts($m["service_service_id"]);
						foreach ($hostList as $host_id) {
							if ($i2) {
								print ",";
							}
							print $host->getHostName($host_id).",".$svc->getServiceName($m["service_service_id"], 1);
							$i2++;
						}
					} else if ($type == 2) {
						$hg = new CentreonHostGroup($this->DB);
						foreach ($hg as $hg_id) {
							$hostList = $svc->getServiceHosts($m["service_service_id"]);
							foreach ($hostList as $host_id) {
								if ($i2) {
									print ",";
								}
								print $host->getHostName($host_id).",".$svc->getServiceName($m["service_service_id"], 1);
								$i2++;
							}
						}
					} else {
						;
					}
				}
				$DBRESULT2->free();
				print "\n";
				$i++;
			}
			$DBRESULT->free();
		}
	}

	/* ****************************************
	 * Add Action
	 */

	public function add($options) {

		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}

		$info = split(";", $options);

		$info[0] = $this->validateName($info[0]);

		if (!$this->serviceCategoryExists($info[0])) {
			$convertionTable = array(0 => "sc_name", 1 => "sc_description");
			$informations = array();
			foreach ($info as $key => $value) {
				$informations[$convertionTable[$key]] = $value;
			}
			return $this->addServiceCategory($informations);
		} else {
			print "Service category ".$info[0]." already exists.\n";
			return 1;
		}
	}

	protected function addServiceCategory($information) {
		if (!isset($information["sc_name"])) {
			return 0;
		} else {
			if (!isset($information["sc_description"]) || $information["sc_description"] == "")
				$information["sc_description"] = $information["sc_name"];

			$request = "INSERT INTO service_categories (sc_name, sc_description, sc_activate) VALUES ('".htmlentities($information["sc_name"], ENT_QUOTES)."', '".htmlentities($information["sc_description"], ENT_QUOTES)."', '1')";
			$DBRESULT =& $this->DB->query($request);

			$sc_id = $this->getServiceCategoryID($information["sc_name"]);

			/**
			 * Update ACL
			 */
			$this->access->updateACL();

			return $sc_id;
		}
	}

	/* ****************************************
	 * Add Action
	 */

	public function setParam($options) {
		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}

		$elem = split(";", $options);
		return $this->setParamServiceCategory($elem[0], $elem[1], $elem[2]);
	}

	protected function setParamServiceCategory($sc_name, $parameter, $value) {

		$value = htmlentities($value, ENT_QUOTES);

		if ($parameter == "alias") {
			$parameter = "description";
		}

		if ($parameter != "name" && $parameter != "description") {
			print "Unknown parameters.\n";
			return 1;
		}

		$sc_id = $this->getServiceCategoryID($sc_name);
		if ($sc_id) {
			$request = "UPDATE service_categories SET sc_$parameter = '$value' WHERE sc_id = '$sc_id'";
			$DBRESULT =& $this->DB->query($request);
			if ($DBRESULT) {
				return 0;
			} else {
				return 1;
			}
		} else {
			print "Service category doesn't exists. Please check your arguments\n";
			return 1;
		}
	}

	/* **************************************
	 * Add childs
	 */

	public function addChild($options) {

		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}

		$elem = split(";", $options);
		if (count($elem) == 2) {
			$id = $this->addTemplateChildServiceCategory($elem[0], $elem[1]);

			/**
			 * Update ACL
			 */
			$this->access->updateACL();

			return $id;
		} else {
			$id =  $this->addChildServiceCategory($elem[0], $elem[1], $elem[2]);

			/**
			 * Update ACL
			 */
			$this->access->updateACL();

			return $id;
		}
	}

	protected function addChildServiceCategory($sc_name, $child_host, $child_service) {

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
		$sc_id = $this->getServiceCategoryID($sc_name);
		if ($sc_id && $host_id && $service_id) {
			$request = "DELETE FROM service_categories_relation WHERE service_service_id = '$service_id' AND sc_id = '$sc_id'";
			$DBRESULT =& $this->DB->query($request);
			$request = "INSERT INTO service_categories_relation (service_service_id, sc_id) VALUES ('$service_id', '$sc_id')";
			$DBRESULT =& $this->DB->query($request);
			if ($DBRESULT) {
				return 0;
			} else {
				return 1;
			}
		} else {
			print "Service category or host doesn't exists. Please check your arguments\n";
			return 1;
		}
	}

	protected function addTemplateChildServiceCategory($sc_name, $child_service) {

		require_once "./class/centreonHost.class.php";
		require_once "./class/centreonService.class.php";

		/*
		 * Get service Child information
		 */
		$service = new CentreonService($this->DB, "STPL");
		$service_id = $service->getServiceTplID(htmlentities($child_service, ENT_QUOTES));

		/*
		 * Add link.
		 */
		$sc_id = $this->getServiceCategoryID($sc_name);
		if ($sc_id && $service_id) {
			$request = "DELETE FROM service_categories_relation WHERE service_service_id = '$service_id' AND sc_id = '$sc_id'";
			$DBRESULT =& $this->DB->query($request);
			$request = "INSERT INTO service_categories_relation (service_service_id, sc_id) VALUES ('$service_id', '$sc_id')";
			$DBRESULT =& $this->DB->query($request);
			if ($DBRESULT) {
				return 0;
			} else {
				return 1;
			}
		} else {
			print "Service category or service doesn't exists. Please check your arguments\n";
			return 1;
		}
	}

	/* **************************************
	 * Add childs
	 */

	public function delChild($options) {
		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}

		$elem = split(";", $options);
		if (count($elem) == 2) {
			return $this->delTemplateChildServiceCategory($elem[0], $elem[1]);
		} else {
			return $this->delChildServiceCategory($elem[0], $elem[1], $elem[2]);
		}
	}

	protected function delChildServiceCategory($sc_name, $child_host, $child_service) {

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
		$sc_id = $this->getServiceCategoryID($sc_name);
		if ($sc_id && $host_id && $service_id) {
			$request = "DELETE FROM service_categories_relation WHERE service_service_id = '$service_id' AND sc_id = '$sc_id'";
			$DBRESULT =& $this->DB->query($request);
			if ($DBRESULT) {
				return 0;
			} else {
				return 1;
			}
		} else {
			print "Service category or service doesn't exists. Please check your arguments\n";
			return 1;
		}
	}

	protected function delTemplateChildServiceCategory($sc_name, $child_service) {

		require_once "./class/centreonHost.class.php";
		require_once "./class/centreonService.class.php";

		/*
		 * Get service Child information
		 */
		$service = new CentreonService($this->DB, "SERVICE");
		$service_id = $service->getServiceTplID(htmlentities($child_service, ENT_QUOTES));

		/*
		 * Add link.
		 */
		$sc_id = $this->getServiceCategoryID($sc_name);
		if ($sc_id && $service_id) {
			$request = "DELETE FROM service_categories_relation WHERE service_service_id = '$service_id' AND sc_id = '$sc_id'";
			$DBRESULT =& $this->DB->query($request);
			if ($DBRESULT) {
				return 0;
			} else {
				return 1;
			}
		} else {
			print "Service category or service doesn't exists. Please check your arguments\n";
			return 1;
		}
	}
}
?>