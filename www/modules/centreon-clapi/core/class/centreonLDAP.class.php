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
 * For more information : command@centreon.com
 *
 * SVN : $URL: http://svn.modules.centreon.com/centreon-clapi/trunk/www/modules/centreon-clapi/core/class/centreonHost.class.php $
 * SVN : $Id: centreonHost.class.php 25 2010-03-30 05:52:19Z jmathis $
 *
 */

require_once "centreonObject.class.php";

/**
 * Class for managing ldap servers
 *
 * @author shotamchay
 */
class CentreonLDAP extends CentreonObject
{
    protected $db;
    const UNKNOWNPARAMETER = "Unknown parameter";

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->baseParams = array('alias'             => '',
                                  'bind_dn'           => '',
                                  'bind_pass'         => '',
                                  'group_base_search' => '',
                                  'group_filter'      => '',
                                  'group_member'      => '',
                                  'group_name'        => '',
                                  'port'              => '',
                                  'protocol_version'  => '',
                                  'user_base_search'  => '',
                                  'user_email'        => '',
                                  'user_filter'       => '',
                                  'user_firstname'    => '',
        						  'user_lastname'     => '',
                                  'user_name'         => '',
                                  'user_pager'        => '',
        						  'user_group'        => '',
                                  'use_ssl'           => '0',
                                  'use_tls'           => '0',
                                  'host'              => '');
    }

    /**
     * Get Ldap Id
     *
     * @param string $name
     * @return mixed | returns null if no ldap id is found
     * @throws CentreonClapiException
     */
    public function getLdapId($name)
    {
        $res = $this->db->query("SELECT ar_id FROM auth_ressource_info WHERE ari_name = 'host' AND ari_value = ?", array($name));
        $row = $res->fetch();
        if (!isset($row['ar_id'])) {
            return null;
        }
        $ldapId = $row['ar_id'];
        unset($res);
        return $ldapId;
    }

    /**
     * Show list of ldap servers
     *
     * @return void
     */
    public function show()
    {
        $sql = "SELECT auth_ressource_info.ar_id, auth_ressource_info.ari_value
        		FROM auth_ressource, auth_ressource_info
        		WHERE auth_ressource.ar_id = auth_ressource_info.ar_id
        		AND ari_name = 'host'
        		ORDER BY ari_value";
        $res = $this->db->query($sql);
        $row = $res->fetchAll();
        echo "id;hostname\n";
        foreach ($row as $ldap) {
            echo $ldap['ar_id'] . $this->delim . $ldap['ari_value'] . "\n";
        }
    }

    /**
     * Add a new ldap server
     *
     * @param string $parameters
     * @throws CentreonClapiException
     */
    public function add($parameters)
    {
        if (!isset($parameters)) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $ldapId = $this->getLdapId($parameters);
        if (isset($ldapId)) {
            throw new CentreonClapiException(self::OBJECTALREADYEXISTS);
        }
        $this->db->query("INSERT INTO auth_ressource (ar_type, ar_enable, ar_order) VALUES ('ldap', '1', 1)");
        $res = $this->db->query("SELECT MAX(ar_id) as lastid FROM auth_ressource WHERE ar_type = 'ldap'");
        $row = $res->fetch();
        $lastId = $row['lastid'];
        unset($res);
        $sql = "INSERT INTO auth_ressource_info (ar_id, ari_name, ari_value) VALUES ";
        $str = "";
        $this->baseParams['host'] = $parameters;
        foreach ($this->baseParams as $paramName => $paramValue) {
            if ($str != "") {
                $str .= ",";
            }
            $str .= "($lastId, ".$this->db->quote($paramName).", ".$this->db->quote($paramValue).")";
        }
        if ($str) {
            $this->db->query($sql . $str);
        }
    }

    /**
     * Delete server
     *
     * @param string $parameters
     * @throws CentreonClapiException
     */
    public function del($parameters)
    {
        if (!isset($parameters)) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $this->db->query("DELETE FROM auth_ressource WHERE ar_id IN (SELECT ar_id FROM auth_ressource_info WHERE ari_name = 'host' AND ari_value = ?)", array($parameters));
    }

    /**
     * Set parameters
     *
     * @param string $parameters
     * @throws CentreonClapiException
     */
    public function setparam($parameters)
    {
        if (!isset($parameters)) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $params = explode($this->delim, $parameters);
        if (count($params) < self::NB_UPDATE_PARAMS) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        if (!isset($this->baseParams[$params[1]]) && $params[1] != "order") {
            throw new CentreonClapiException(self::UNKNOWNPARAMETER);
        }
        $ldapId = $this->getLdapId($params[0]);
        if (!isset($ldapId)) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND);
        }
        if ($params[1] != "order") {
            $this->db->query("UPDATE auth_ressource_info
            				  SET ari_value = ?
            				  WHERE ari_name = ?
            				  AND ar_id = ?", array($params[2],
                                                    $params[1],
                                                    $ldapId));
        } else {
            $this->db->query("UPDATE auth_ressource
            				  SET ar_order = ?
            				  WHERE ar_id = ?", array($params[2], $ldapId));
        }
    }

    /**
     * Set contact template
     *
     * @param string $parameters
     * @return void
     * @throws CentreonClapiException
     */
    public function setcontacttemplate($parameters)
    {
        if (!isset($parameters)) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $sql = "SELECT contact_id FROM contact WHERE contact_name = ? AND contact_register = 0";
        $res = $this->db->query($sql, array($parameters));
        $row = $res->fetch();
        if (!isset($row['contact_id'])) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND);
        }
        $contactId = $row['contact_id'];
        unset($res);
        $this->db->query("UPDATE options SET `value` = ? WHERE `key` = 'ldap_contact_tmpl'", array($contactId));
    }
}