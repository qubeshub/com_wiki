<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2015 HUBzero Foundation, LLC.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * HUBzero is a registered trademark of Purdue University.
 *
 * @package   hubzero-cms
 * @author    Shawn Rice <zooley@purdue.edu>
 * @copyright Copyright 2005-2015 HUBzero Foundation, LLC.
 * @license   http://opensource.org/licenses/MIT MIT
 */

namespace Components\Wiki\Models;

use Components\Wiki\Helpers\Parser;
use Hubzero\Base\Model;
use Hubzero\User\Profile;
use Hubzero\Utility\String;
use Hubzero\Base\ItemList;
use Request;
use Date;
use Lang;

require_once(dirname(__DIR__) . DS . 'tables' . DS . 'comment.php');

/**
 * Wiki model for a comment
 */
class Comment extends Model
{
	/**
	 * Table class name
	 *
	 * @var string
	 */
	protected $_tbl_name = '\\Components\\Wiki\\Tables\\Comment';

	/**
	 * \Hubzero\User\Profile
	 *
	 * @var object
	 */
	private $_creator = NULL;

	/**
	 * ItemList
	 *
	 * @var object
	 */
	private $_comments = NULL;

	/**
	 * Comment count
	 *
	 * @var integer
	 */
	private $_comments_count = NULL;

	/**
	 * Returns a reference to a wiki comment model
	 *
	 * @param   mixed   $oid  ID (int) or alias (string)
	 * @return  object
	 */
	static function &getInstance($oid=0)
	{
		static $instances;

		if (!isset($instances))
		{
			$instances = array();
		}

		if (!isset($instances[$oid]))
		{
			$instances[$oid] = new self($oid);
		}

		return $instances[$oid];
	}

	/**
	 * Has the offering started?
	 *
	 * @return  boolean
	 */
	public function isReported()
	{
		if ($this->get('status') == self::APP_STATE_FLAGGED)
		{
			return true;
		}
		return false;
	}

	/**
	 * Return a formatted timestamp
	 *
	 * @param   string  $as What data to return
	 * @return  boolean
	 */
	public function created($as='')
	{
		switch (strtolower($as))
		{
			case 'date':
				return Date::of($this->get('created'))->toLocal(Lang::txt('DATE_FORMAT_HZ1'));
			break;

			case 'time':
				return Date::of($this->get('created'))->toLocal(Lang::txt('TIME_FORMAT_HZ1'));
			break;

			default:
				return $this->get('created');
			break;
		}
	}

	/**
	 * Get the creator of this entry
	 *
	 * Accepts an optional property name. If provided
	 * it will return that property value. Otherwise,
	 * it returns the entire user object
	 *
	 * @param   string $property What data to return
	 * @param   mixed  $default  Default value
	 * @return  mixed
	 */
	public function creator($property=null, $default=null)
	{
		if (!($this->_creator instanceof Profile))
		{
			$this->_creator = Profile::getInstance($this->get('created_by'));
			if (!$this->_creator)
			{
				$this->_creator = new Profile();
			}
		}
		if ($property)
		{
			$property = ($property == 'id') ? 'uidNumber' : $property;
			if ($property == 'picture')
			{
				return $this->_creator->getPicture($this->get('anonymous'));
			}
			return $this->_creator->get($property, $default);
		}
		return $this->_creator;
	}

	/**
	 * Get the state of the entry as either text or numerical value
	 *
	 * @param   string  $as      Format to return state in [text, number]
	 * @param   integer $shorten Number of characters to shorten text to
	 * @return  mixed   String or Integer
	 */
	public function content($as='parsed', $shorten=0)
	{
		$as = strtolower($as);
		$options = array();

		switch ($as)
		{
			case 'parsed':
				if ($this->get('chtml'))
				{
					return $this->get('chtml');
				}
				if ($this->get('parsed'))
				{
					return $this->get('parsed');
				}

				$p = Parser::getInstance();

				$wikiconfig = array(
					'option'   => Request::getCmd('option', 'com_wiki'),
					'scope'    => Request::getVar('scope'),
					'pagename' => Request::getVar('pagename'),
					'pageid'   => $this->get('pageid'),
					'filepath' => '',
					'domain'   => Request::getVar('group', '')
				);

				$this->set('parsed', $p->parse(stripslashes($this->get('ctext')), $wikiconfig));
				if ($shorten)
				{
					$content = String::truncate($this->get('parsed'), $shorten, array('html' => true));
					return $content;
				}

				return $this->get('parsed');
			break;

			case 'clean':
				$content = strip_tags($this->content('parsed'));
			break;

			case 'raw':
			default:
				$content = $this->get('ctext');
			break;
		}

		if ($shorten)
		{
			$content = String::truncate($content, $shorten, $options);
		}

		return $content;
	}

	/**
	 * Generate and return various links to the entry
	 * Link will vary depending upon action desired, such as edit, delete, etc.
	 *
	 * @param   string $type The type of link to return
	 * @return  boolean
	 */
	public function link($type='')
	{
		if (!isset($this->_base))
		{
			$this->_base  = 'index.php?option=' . Request::getCmd('option', 'com_wiki') . '&scope=' . Request::getVar('scope') . '&pagename=' . Request::getVar('pagename');
		}
		$task = Request::getVar('cn', '') ? 'action' : 'task';

		$link = $this->_base;

		// If it doesn't exist or isn't published
		switch (strtolower($type))
		{
			case 'edit':
				$link .= '&' . $task . '=editcomment&comment=' . $this->get('id');
			break;

			case 'delete':
				$link .= '&' . $task . '=removecomment&comment=' . $this->get('id');
			break;

			case 'reply':
				$link .= '&' . $task . '=addcomment&parent=' . $this->get('id');
			break;

			case 'report':
				$link = 'index.php?option=com_support&task=reportabuse&category=wikicomment&id=' . $this->get('id') . '&parent=' . $this->get('pageid');
			break;

			case 'permalink':
			default:
				$link .= '&' . $task . '=comments#c' . $this->get('id');
			break;
		}

		return $link;
	}

	/**
	 * Get a list or count of replies
	 *
	 * @param   string  $rtrn    Data format to return
	 * @param   array   $filters Filters to apply to data fetch
	 * @param   boolean $clear   Clear cached data?
	 * @return  mixed
	 */
	public function replies($rtrn='list', $filters=array(), $clear=false)
	{
		if (!isset($filters['pageid']))
		{
			$filters['pageid'] = $this->get('pageid');
		}
		if (!isset($filters['parent']))
		{
			$filters['parent'] = $this->get('id');
		}
		if (!isset($filters['status']))
		{
			$filters['status'] = array(self::APP_STATE_PUBLISHED, self::APP_STATE_FLAGGED);
		}

		switch (strtolower($rtrn))
		{
			case 'count':
				if (!is_numeric($this->_comments_count) || $clear)
				{
					$this->_comments_count = 0;

					if (!$this->_comments)
					{
						$c = $this->comments('list', $filters);
					}
					foreach ($this->_comments as $com)
					{
						$this->_comments_count++;
						if ($com->replies())
						{
							foreach ($com->replies() as $rep)
							{
								$this->_comments_count++;
								if ($rep->replies())
								{
									$this->_comments_count += $rep->replies()->total();
								}
							}
						}
					}
				}
				return $this->_comments_count;
			break;

			case 'list':
			case 'results':
			default:
				if (!($this->_comments instanceof ItemList) || $clear)
				{
					$results = $this->_tbl->find('list', $filters);

					if ($results)
					{
						foreach ($results as $key => $result)
						{
							$results[$key] = new Comment($result);
						}
					}
					else
					{
						$results = array();
					}
					$this->_comments = new ItemList($results);
				}
				return $this->_comments;
			break;
		}
	}
}

