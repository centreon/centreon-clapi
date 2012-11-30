=================
Poller management
=================

List available pollers
----------------------

In order to list available pollers, use the **POLLERLIST** command::

  [root@centreon core]# ./centreon -u admin -p centreon -a POLLERLIST
  1       Local Poller
  2       Remote Poller

Where 1 is the id of "Local Poller" and 2 is the id of "Remote Poller".


Generate local configuration files for a poller
-----------------------------------------------

In order to generate configuration files for poller "Local Poller" of id 1, use the **POLLERGENERATE** command::

  [root@centreon core]# ./centreon -u admin -p centreon -a POLLERGENERATE -v 1
  Configuration files generated for poller 1


Test monitoring engine configuration of a poller
------------------------------------------------

In order to test configuration files for poller "Remote Poller" of id 2, use the **POLLERTEST** command::

  [root@centreon core]# ./centreon -u admin -p centreon -a POLLERTEST -v 2
  OK: Nagios Poller 2 can restart without problem...


Move monitoring engine configuration files
------------------------------------------

In order to move configuration files for poller "Local Poller" of id 1 to the final engine directory, use the **CFGMOVE** command::

  [root@centreon core]# ./centreon -u admin -p centreon -a CFGMOVE -v 1
  OK: All configuration files copied with success


Restart monitoring engine of a poller
-------------------------------------

In order to restart the monitoring process on poller "Local Poller" of id 1, use the **POLLERRESTART** command::

  [root@centreon core]# ./centreon -u admin -p centreon -a POLLERRESTART -v 1
  Running configuration check...done.
  Stopping nagios: .done.
  Starting nagios: done.


Reload monitoring engine of a poller
------------------------------------

In order to reload the monitoring process on poller "Remote Poller" of id 2, use **POLLERRELOAD** command::

  [root@centreon core]# ./centreon -u admin -p centreon -a POLLERRELOAD -v 2
  Running configuration check...done.
  Reloading nagios configuration...done


Send Centreon trap configuration files to poller
------------------------------------------------

In order to send trap configuration files to a remote poller, use the **SENDTRAPCFG** command::

  [root@localhost] ./centreon -u admin -p centreon -a SENDTRAPCFG -v "2"
  Generating SNMPTT configuration files...
  224 traps for 7 manufacturers are defined.
  SNMPTT configuration files generated.
  Return code end : 0
