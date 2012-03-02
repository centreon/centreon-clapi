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
	private $access;

	protected $version;

	public function __construct($DB) {
		$this->DB = $DB;

		/**
		 * Enable Access Object
		 */
		$this->access = new CentreonACLResources($this->DB);

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
	 * encode with htmlentities a string
	 * @param unknown_type $string
	 */
	protected function encodeInHTML($string) {
	    if (!strncmp($this->version, "2.1", 3)) {
            $string = htmlentities($string, ENT_QUOTES, "UTF-8");
	    }
	    return $string;
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
		$DBRESULT =& $this->DB->query("SELECT sg_name, sg_id FROM servicegroup WHERE sg_name = '".$this->encodeInHTML($name)."'");
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
			print "No options defined.\n";
			$this->return_code = 1;
			return 1;
		}
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
		$request = "DELETE FROM servicegroup WHERE sg_name LIKE '".$this->encodeInHTML($name)."'";
		$DBRESULT =& $this->DB->query($request);

		/**
		 * Update ACL
		 */
		$this->access->updateACL();

		return 0;
	}

	/** ****************************************
	 * Dislay all SG
	 */
	public function show($search = NULL) {
		/*
		 * Set Search
		 */
		$searchStr = "";
		if (isset($search) && $search != "") {
			$searchStr = " WHERE sg_name LIKE '%".$this->encodeInHTML($search)."%'";
		}


		/*
		 * Get Child informations
		 */
		require_once "./class/centreonHost.class.php";
		require_once "./class/centreonService.class.php";
		$host = new CentreonHost();
		$svc = new CentreonService();

		$request = "SELECT sg_id, sg_name, sg_alias FROM servicegroup $searchStr ORDER BY sg_name";
		$DBRESULT =& $this->DB->query($request);
		$i = 0;
		while ($data =& $DBRESULT->fetchRow()) {
			if ($i == 0) {
				print "Name;Alias;Members\n";
			}
			print html_entity_decode($data["sg_name"]).";".html_entity_decode($data["sg_alias"]).";";

			/*
			 * Get Childs informations
			 */
			$request = "SELECT host_host_id, service_service_id FROM servicegroup_relation WHERE servicegroup_sg_id = '".$data["sg_id"]."'";
			$DBRESULT2 =& $this->DB->query($request);
			$i2 = 0;
			while ($m =& $DBRESULT2->fetchRow()) {
				if ($i2) {
					print ",";
				}
				print $host->getObjectName($m["host_host_id"]).",".$svc->getObjectName($m["service_service_id"]);
				$i2++;
			}
			$DBRESULT2->free();
			print "\n";
			$i++;
		}
		$DBRESULT->free();

	}

	/** ****************************************
	 * Export all SG
	 */
	public function export() {

		/*
		 * Get Child informations
		 */
		require_once "./class/centreonHost.class.php";
		require_once "./class/centreonService.class.php";
		$host = new CentreonHost($this->DB, "HOST");
		$svc = new CentreonService($this->DB, "SERVICE");

		$request = "SELECT sg_id, sg_name, sg_alias FROM servicegroup ORDER BY sg_name";
		$DBRESULT =& $this->DB->query($request);
		while ($data =& $DBRESULT->fetchRow()) {
			print "SG;ADD;".html_entity_decode($data["sg_name"]).";".html_entity_decode($data["sg_alias"])."\n";

			/*
			 * Get Childs informations
			 */
			$request = "SELECT host_host_id, service_service_id FROM servicegroup_relation WHERE servicegroup_sg_id = '".$data["sg_id"]."'";
			$DBRESULT2 =& $this->DB->query($request);
			while ($m =& $DBRESULT2->fetchRow()) {
				print "SG;ADDCHILD;".html_entity_decode($data["sg_name"]).";".$host->getHostName($m["host_host_id"]).";".$svc->getServiceName($m["service_service_id"], 1)."\n";
			}
			$DBRESULT2->free();
		}
		$DBRESULT->free();

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
			if (!isset($information["sg_alias"]) || $information["sg_alias"] == "") {
				$information["sg_alias"] = $information["sg_name"];
			}

			$request = "INSERT INTO servicegroup (sg_name, sg_alias, sg_activate) VALUES ('".$this->encodeInHTML($information["sg_name"])."', '".$this->encodeInHTML($information["sg_alias"])."', '1')";
			$DBRESULT =& $this->DB->query($request);

			$sg_id = $this->getServiceGroupID($information["sg_name"]);

			/**
			 * Update ACL
			 */
			$this->access->updateACL();

			return $sg_id;
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
		return $this->setParamServiceGroup($elem[0], $elem[1], $elem[2]);
	}

	protected function setParamServiceGroup($sg_name, $parameter, $value) {

		$value = $this->encodeInHTML($value);

		if ($parameter != "name" && $parameter != "alias" && $parameter != "comment") {
			print "Unknown parameters.\n";
			return 1;
		}

		$sg_id = $this->getServiceGroupID($sg_name);
		if ($sg_id) {
			$request = "UPDATE servicegroup SET sg_$parameter = '$value' WHERE sg_id = '$sg_id'";
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
		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}

		$elem = split(";", $options);
		if (!isset($elem[2])) {
			$elem[2] = "";
		}
		return $this->addChildServiceGroup($elem[0], $elem[1], $elem[2]);
	}

	protected function addChildServiceGroup($sg_name, $child_host, $child_service) {

		require_once "./class/centreonHost.class.php";
		require_once "./class/centreonService.class.php";

		/*
		 * Get host Child informations
		 */
		$host = new CentreonHost($this->DB, "HOST");
		$host_id = $host->getHostID($this->encodeInHTML($child_host));

		/*
		 * Get service Child information
		 */
		$service = new CentreonService($this->DB, "SERVICE");
		$service_id = $service->getServiceID($host_id, $this->encodeInHTML($child_service));

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
				/**
				 * Update ACL
				 */
				$this->access->updateACL();

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
		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}

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
		$host_id = $host->getHostID($this->encodeInHTML($child_host));

		/*
		 * Get service Child information
		 */
		$service = new CentreonService($this->DB, "SERVICE");
		$service_id = $service->getServiceID($host_id, $this->encodeInHTML($child_service));

		/*
		 * Add link.
		 */
		$sg_id = $this->getServiceGroupID($sg_name);
		if ($sg_id && $host_id && $service_id) {
			$request = "DELETE FROM servicegroup_relation WHERE host_host_id = '$host_id' AND service_service_id = '$service_id' AND servicegroup_sg_id = '$sg_id'";
			$DBRESULT =& $this->DB->query($request);
				/**
				 * Update ACL
				 */
				$this->access->updateACL();

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