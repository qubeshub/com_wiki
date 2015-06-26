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
use User;
use Date;

/**
 * Wiki table class for page version
 */
class Revision extends \JTable
{
	/**
	 * Constructor
	 *
	 * @param   object  &$db  Database
	 * @return  void
	 */
	public function __construct($db)
	{
		parent::__construct('#__wiki_version', 'id', $db);
	}

	/**
	 * Validate data
	 *
	 * @return  boolean  True if data is valid
	 */
	public function check()
	{
		$this->minor_edit = intval($this->minor_edit);
		$this->approved   = intval($this->approved);
		$this->version    = intval($this->version);
		if ($this->version <= 0)
		{
			$this->version = 1;
		}

		$this->pageid = intval($this->pageid);
		if (!$this->pageid)
		{
			$this->setError(Lang::txt('This revision is missing its page ID.'));
		}
		if (trim($this->pagetext) == '')
		{
			$this->setError(Lang::txt('Please provide content. A wiki page cannot be empty.'));
		}

		if ($this->getError())
		{
			return false;
		}

		if (!$this->id)
		{
			$this->created    = Date::toSql();
			$this->created_by = User::get('id');
		}
		$this->length = strlen($this->pagetext);

		return true;
	}

	/**
	 * Load a record by the page/version combination and bind to $this
	 *
	 * @param   integer  $pageid   Page ID
	 * @param   integer  $version  Version number
	 * @return  boolean  True on success
	 */
	public function loadByVersion($pageid, $version=0)
	{
		if (!$pageid)
		{
			return false;
		}

		if ($version)
		{
			$query = "SELECT * FROM $this->_tbl WHERE pageid=" . $this->_db->quote($pageid) . " AND version=" . $this->_db->quote($version) . " AND approved != 2";
		}
		else
		{
			$query = "SELECT * FROM $this->_tbl WHERE pageid=" . $this->_db->quote($pageid) . " AND approved=" . $this->_db->quote('1') . " ORDER BY version DESC LIMIT 1";
		}
		$this->_db->setQuery($query);
		if ($result = $this->_db->loadAssoc())
		{
			return $this->bind($result);
		}

		$this->setError($this->_db->getErrorMsg());
		return false;
	}

	/**
	 * Get a list of all contributors on a wiki page
	 *
	 * @return  array
	 */
	public function getContributors()
	{
		$this->_db->setQuery("SELECT DISTINCT created_by AS id FROM $this->_tbl WHERE pageid=" . $this->_db->quote($this->pageid) . " AND approved=" . $this->_db->quote('1'));
		$contributors = $this->_db->loadObjectList();

		$cons = array();
		if (count($contributors) > 0)
		{
			foreach ($contributors as $con)
			{
				$cons[] = $con->id;
			}
		}
		return $cons;
	}

	/**
	 * Get a count of all revisions for a page
	 *
	 * @return  integer
	 */
	public function getRevisionCount()
	{
		$this->_db->setQuery("SELECT COUNT(*) FROM $this->_tbl WHERE pageid=" . $this->_db->quote($this->pageid) . " AND approved=" . $this->_db->quote('1'));
		return $this->_db->loadResult();
	}

	/**
	 * Get all the revision numbers for a page
	 *
	 * @param   integer  $pageid  Page ID
	 * @return  array
	 */
	public function getRevisionNumbers($pageid=NULL)
	{
		if (!$pageid)
		{
			$pageid = $this->pageid;
		}
		$this->_db->setQuery("SELECT DISTINCT version FROM $this->_tbl WHERE pageid=" . $this->_db->quote($pageid) . " AND approved=" . $this->_db->quote('1') . " ORDER BY version DESC");
		return $this->_db->loadObjectList();
	}

	/**
	 * Get all the revisions for a page
	 *
	 * @param   integer  $pageid  Page ID
	 * @return  array
	 */
	public function getRevisions($pageid=NULL)
	{
		if (!$pageid)
		{
			$pageid = $this->pageid;
		}
		return $this->getRecords(array(
			'pageid'   => $pageid,
			'approved' => array(0, 1))
		);
	}

	/**
	 * Get a record count based off of filters passed
	 *
	 * @param   array    $filters  Filters to build from
	 * @return  integer
	 */
	public function getRecordsCount($filters=array())
	{
		$query = "SELECT COUNT(*) " . $this->buildQuery($filters);

		$this->_db->setQuery($query);
		return $this->_db->loadResult();
	}

	/**
	 * Get a list of records based off of filters passed
	 *
	 * @param   array  $filters  Filters to build from
	 * @return  array
	 */
	public function getRecords($filters=array())
	{
		$query  = "SELECT r.id, r.pageid, r.version, r.created, r.created_by, r.minor_edit, r.approved, r.summary, r.length, u.name AS created_by_name, u.username AS created_by_alias ";
		$query .= $this->buildQuery($filters);

		if (!isset($filters['sortby']) || $filters['sortby'] == '')
		{
			$filters['sortby'] = 'version DESC, created DESC';
		}

		$query .= " ORDER BY " . $filters['sortby'];

		if (isset($filters['limit']) && $filters['limit'] != 0  && $filters['limit'] != 'all')
		{
			$query .= " LIMIT " . (int) $filters['start'] . "," . (int) $filters['limit'];
		}

		$this->_db->setQuery($query);
		return $this->_db->loadObjectList();
	}

	/**
	 * Build an SQL statement based on filters passed
	 *
	 * @param   array   $filters  Filters to build from
	 * @return  string  SQL
	 */
	public function buildQuery($filters)
	{
		$query = "FROM $this->_tbl AS r LEFT JOIN #__users AS u ON r.created_by=u.id";

		$where = array();

		if (isset($filters['pageid']))
		{
			$where[] = "r.pageid=" . $this->_db->quote((int) $filters['pageid']);
		}
		if (isset($filters['search']) && $filters['search'])
		{
			$where[] = "LOWER(r.pagehtml) LIKE " . $this->_db->quote('%' . strtolower($filters['search']) . '%');
		}
		if (isset($filters['approved']) && $filters['approved'])
		{
			if (is_array($filters['approved']))
			{
				$filters['approved'] = array_map('intval', $filters['approved']);
				$where[] = "r.approved IN (" . implode(',', $filters['approved']) . ")";
			}
			else
			{
				$where[] = "r.approved=" . $this->_db->quote($filters['approved']);
			}
		}

		if (count($where) > 0)
		{
			$query .= " WHERE " . implode(" AND ", $where);
		}

		return $query;
	}
}

