<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2015 HUBzero Foundation, LLC.
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
 * @copyright Copyright 2005-2015 HUBzero Foundation, LLC.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

namespace Components\Wiki\Tables;

use Lang;
use Date;

/**
 * Wiki table class for logging links
 */
class Link extends \JTable
{
	/**
	 * Constructor
	 *
	 * @param   object  &$db  Database
	 * @return  void
	 */
	public function __construct($db)
	{
		parent::__construct('#__wiki_page_links', 'id', $db);
	}

	/**
	 * Validate data
	 *
	 * @return  boolean  True if valid, false if not
	 */
	public function check()
	{
		$this->page_id = intval($this->page_id);
		if (!$this->page_id)
		{
			$this->setError(Lang::txt('COM_WIKI_LOGS_MUST_HAVE_PAGE_ID'));
		}

		$this->scope = strtolower($this->scope);
		if (!$this->scope)
		{
			$this->setError(Lang::txt('COM_WIKI_LOGS_MUST_HAVE_SCOPE'));
		}

		if ($this->getError())
		{
			return false;
		}

		if (!$this->id)
		{
			$this->timestamp = Date::toSql();
		}

		return true;
	}

	/**
	 * Retrieve all entries for a specific page
	 *
	 * @param   integer  $page_id  Page ID
	 * @return  array
	 */
	public function find($page_id=null)
	{
		$page_id = $page_id ?: $this->page_id;

		if (!$page_id)
		{
			return null;
		}

		$this->_db->setQuery("SELECT * FROM $this->_tbl WHERE page_id=" . $this->_db->quote($page_id) . " ORDER BY `timestamp` DESC");
		return $this->_db->loadObjectList();
	}

	/**
	 * Delete all entries for a specific page
	 *
	 * @param   integer  $pid  Page ID
	 * @return  boolean  True on success
	 */
	public function deleteByPage($page_id=null)
	{
		$page_id = $page_id ?: $this->page_id;

		if (!$page_id)
		{
			return false;
		}

		$this->_db->setQuery("DELETE FROM $this->_tbl WHERE page_id=" . $this->_db->quote($page_id));
		if (!$this->_db->query())
		{
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		return true;
	}

	/**
	 * Delete all entries for a specific page
	 *
	 * @param   integer  $pid  Page ID
	 * @return  boolean  True on success
	 */
	public function addLinks($links=array())
	{
		if (count($links) <= 0)
		{
			return true;
		}

		$timestamp = Date::toSql();

		$query = "INSERT INTO $this->_tbl (`page_id`, `timestamp`, `scope`, `scope_id`, `link`, `url`) VALUES ";

		$inserts = array();
		foreach ($links as $link)
		{
			$inserts[] = "(" . $this->_db->quote($link['page_id']) . "," .
								$this->_db->quote($timestamp) . "," .
								$this->_db->quote($link['scope']) . "," .
								$this->_db->quote($link['scope_id']) . "," .
								$this->_db->quote($link['link']) . "," .
								$this->_db->quote($link['url']) .
							")";
		}

		$query .= implode(',', $inserts) . ";";

		$this->_db->setQuery($query);
		if (!$this->_db->query())
		{
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		return true;
	}

	/**
	 * Update entries
	 *
	 * @param   integer  $pid   Page ID
	 * @param   array    $links  Entries
	 * @return  boolean  True on success
	 */
	public function updateLinks($page_id, $data=array())
	{
		$links = array();
		foreach ($data as $data)
		{
			// Eliminate duplicates
			$links[$data['link']] = $data;
		}

		if ($rows = $this->find($page_id))
		{
			foreach ($rows as $row)
			{
				if (!isset($links[$row->link]))
				{
					// Link wasn't found, delete it
					$this->delete($row->id);
				}
				else
				{
					unset($links[$row->link]);
				}
			}
		}

		if (count($links) > 0)
		{
			$this->addLinks($links);
		}
		return true;
	}
}

