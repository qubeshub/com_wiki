<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2015 Purdue University. All rights reserved.
 *
 * This file is part of: The HUBzero(R) Platform for Scientific Collaboration
 *
 * The HUBzero(R) Platform for Scientific Collaboration (HUBzero) is free
 * software: you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software
 * Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * HUBzero is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * HUBzero is a registered trademark of Purdue University.
 *
 * @package   hubzero-cms
 * @author    Shawn Rice <zooley@purdue.edu>
 * @copyright Copyright 2005-2015 Purdue University. All rights reserved.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

namespace Components\Wiki\Tables;

use Lang;

/**
 * Wiki table class for logging events
 */
class Log extends \JTable
{
	/**
	 * Constructor
	 *
	 * @param   object  &$db  Database
	 * @return  void
	 */
	public function __construct(&$db)
	{
		parent::__construct('#__wiki_log', 'id', $db);
	}

	/**
	 * Validate data
	 *
	 * @return  boolean  True if valid, false if not
	 */
	public function check()
	{
		$this->pid = intval($this->pid);
		if (!$this->pid)
		{
			$this->setError(Lang::txt('COM_WIKI_LOGS_MUST_HAVE_PAGE_ID'));
		}

		$this->uid = intval($this->uid);
		if (!$this->uid)
		{
			$this->setError(Lang::txt('COM_WIKI_LOGS_MUST_HAVE_USER_ID'));
		}

		if ($this->getError())
		{
			return false;
		}

		return true;
	}

	/**
	 * Retrieve all entries for a specific page
	 *
	 * @param   integer  $pid  Page ID
	 * @return  array
	 */
	public function getLogs($pid=null)
	{
		$pid = $pid ?: $this->pid;

		if (!$pid)
		{
			return null;
		}

		$this->_db->setQuery("SELECT * FROM $this->_tbl WHERE pid=" . $this->_db->quote($pid) . " ORDER BY `timestamp` DESC");
		return $this->_db->loadObjectList();
	}

	/**
	 * Delete all entries for a specific page
	 *
	 * @param   integer  $pid  Page ID
	 * @return  boolean  True on success
	 */
	public function deleteLogs($pid=null)
	{
		$pid = $pid ?: $this->pid;

		if (!$pid)
		{
			return false;
		}

		$this->_db->setQuery("DELETE FROM $this->_tbl WHERE pid=" . $this->_db->quote($pid));
		if (!$this->_db->query())
		{
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		return true;
	}
}

