<?php
/**
 * @file    ZabbixApi.class.php
 * @brief   Class file for the implementation of the class ZabbixApi.
 *
 * Implement your customizations in this file.
 *
 * This file is part of PhpZabbixApi.
 *
 * PhpZabbixApi is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PhpZabbixApi is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PhpZabbixApi.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @copyright   GNU General Public License
 * @author      confirm IT solutions GmbH, Rathausstrase 14, CH-6340 Baar
 *
 * @version     $Id: ZabbixApi.class.php 138 2012-10-08 08:00:24Z dbarton $
 */

/**
 * @brief   Concrete class for the Zabbix API.
 */

class ZabbixApi extends ZabbixApiAbstract
{
	
	/**
	 * @brief   Auth string.
	 */
	
	private $auth = '';
	
	
	/**
	 * @brief   Class constructor.
	 *
	 * @param   $apiUrl     API url (e.g. http://FQDN/zabbix/api_jsonrpc.php)
	 * @param   $user       Username.
	 * @param   $password   Password.
	 */
	
	public function __construct($apiUrl='', $user='', $password='')
	{
		if($apiUrl)
			$this->setApiUrl($apiUrl);
	
		if($user && $password)
			$this->auth = $this->userLogin(array('user' => $user, 'password' => $password));
	}
	
	

	public function getAuth() {
		return $this->auth;
	}
	
}

?>
