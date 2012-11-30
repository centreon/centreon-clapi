==================
LDAP configuration
==================

Overview
--------

Object name: **LDAP**


Show
----

In order to list available LDAP servers, use the **SHOW** action::

  [root@centreon ~]# ./centreon -u admin -p centreon -o LDAP -a show 
  id;hostname
  1;srv-ldap.company.net
  [...]

Columns are the following:

======= =============================
Order	Description
======= =============================
1	ID

2	Host name (or host address)
======= =============================


Add
---

In order to add an LDAP server, use the **ADD** action::

  [root@centreon ~]# ./centreon -u admin -p centreon -o LDAP -a add -v "srv-ad.company.net" 

Required fields are:

======= =============================
Order	Description
======= =============================
1	Host name (or host address)
======= =============================


Del
---

If you want to remove an LDAP server, use the **DEL** action. The Host Name is used for identifying the LDAP server to delete::

  [root@centreon ~]# ./centreon -u admin -p centreon -o LDAP -a del -v "srv-ad.company.net" 


Setcontacttemplate
------------------

When importing users from LDAP servers, you may want to tie them to a contact template. In order to do so, you have to set a contact template with the **SETCONTACTTEMPLATE** action::

  [root@centreon ~]# ./centreon -u admin -p centreon -o LDAP -a setcontacttemplate -v "my_contact_template" 


Setparam
--------

If you want to change a specific parameter of an LDAP server, use the **SETPARAM** action. The Host Name is used for identifying the LDAP server to update::

  [root@centreon ~]# ./centreon -u admin -p centreon -o LDAP -a setparam -v "srv-ad.company.net;use_ssl;1" 
