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

namespace Components\Wiki\Site\Controllers;

use Components\Wiki\Models\Book;
use Components\Wiki\Models\Page;
use Components\Wiki\Tables;
use Hubzero\Component\SiteController;
use Hubzero\Content\Server;
use Hubzero\Utility\Number;
use Filesystem;
use Exception;
use Request;
use User;
use Lang;
use Date;

/**
 * Wiki controller class for media
 */
class Media extends SiteController
{
	/**
	 * Constructor
	 *
	 * @param      array $config Optional configurations
	 * @return     void
	 */
	public function __construct($config=array())
	{
		$this->_base_path = dirname(__DIR__);
		if (isset($config['base_path']))
		{
			$this->_base_path = $config['base_path'];
		}

		$this->_sub = false;
		if (isset($config['sub']))
		{
			$this->_sub = $config['sub'];
		}

		$this->_group = false;
		if (isset($config['group']))
		{
			$this->_group = $config['group'];
		}

		$this->book = new Book(($this->_group ? $this->_group : '__site__'));

		parent::__construct($config);
	}

	/**
	 * Execute a task
	 *
	 * @return     void
	 */
	public function execute()
	{
		$this->page = $this->book->page();

		parent::execute();
	}

	/**
	 * Download a wiki file
	 *
	 * @return     void
	 */
	public function downloadTask()
	{
		$this->page->set('pagename', trim(Request::getVar('pagename', '', 'default', 'none', 2)));

		// Instantiate an attachment object
		$attachment = new Tables\Attachment($this->database);
		if ($this->page->get('namespace') == 'image' || $this->page->get('namespace') == 'file')
		{
			$attachment->filename = $this->page->denamespaced();
		}
		$attachment->filename = urldecode($attachment->filename);

		// Get the scope of the parent page the file is attached to
		if (!$this->scope)
		{
			$this->scope = trim(Request::getVar('scope', ''));
		}
		$segments = explode('/', $this->scope);
		$pagename = array_pop($segments);
		$scope = implode('/', $segments);

		// Get the parent page the file is attached to
		$this->page = new Page($pagename, $scope);

		// Load the page
		if ($this->page->exists())
		{
			// Check if the page is group restricted and the user is authorized
			if ($this->page->get('group_cn') != '' && $this->page->get('access') != 0 && !$this->page->access('view'))
			{
				throw new Exception(Lang::txt('COM_WIKI_WARNING_NOT_AUTH'), 403);
			}
		}
		else if ($this->page->get('namespace') == 'tmp')
		{
			$this->page->set('id', $this->page->denamespaced());
		}
		else
		{
			throw new Exception(Lang::txt('COM_WIKI_PAGE_NOT_FOUND'), 404);
		}

		// Ensure we have a path
		if (empty($attachment->filename))
		{
			throw new Exception(Lang::txt('COM_WIKI_FILE_NOT_FOUND'), 404);
		}

		// Does the path start with a slash?
		$attachment->filename = DS . ltrim($attachment->filename, DS);

		// Add root
		$filename = $attachment->filespace() . DS . $this->page->get('id') . $attachment->filename;

		// Ensure the file exist
		if (!file_exists($filename))
		{
			throw new Exception(Lang::txt('COM_WIKI_FILE_NOT_FOUND') . ' ' . $filename, 404);
		}

		// Initiate a new content server and serve up the file
		$xserver = new Server();
		$xserver->filename($filename);
		$xserver->disposition('inline');
		$xserver->acceptranges(false); // @TODO fix byte range support

		if (!$xserver->serve())
		{
			// Should only get here on error
			throw new Exception(Lang::txt('COM_WIKI_SERVER_ERROR'), 500);
		}
		else
		{
			exit;
		}
		return;
	}

	/**
	 * Upload a file to the wiki via AJAX
	 *
	 * @return     string
	 */
	public function ajaxUploadTask()
	{
		// Check if they're logged in
		if (User::isGuest())
		{
			echo json_encode(array('error' => Lang::txt('COM_WIKI_WARNING_LOGIN')));
			return;
		}

		// Ensure we have an ID to work with
		$listdir = Request::getInt('listdir', 0);
		if (!$listdir)
		{
			echo json_encode(array('error' => Lang::txt('COM_WIKI_NO_ID')));
			return;
		}

		// max upload size
		$sizeLimit = $this->book->config('maxAllowed', 40000000);

		// get the file
		if (isset($_GET['qqfile']))
		{
			$stream = true;
			$file = $_GET['qqfile'];
			$size = (int) $_SERVER["CONTENT_LENGTH"];
		}
		elseif (isset($_FILES['qqfile']))
		{
			$stream = false;
			$file = $_FILES['qqfile']['name'];
			$size = (int) $_FILES['qqfile']['size'];
		}
		else
		{
			echo json_encode(array('error' => Lang::txt('COM_WIKI_ERROR_NO_FILE')));
			return;
		}

		$attachment = new Tables\Attachment($this->database);

		// define upload directory and make sure its writable
		$path = $attachment->filespace() . DS . $listdir;

		if (!is_dir($path))
		{
			if (!Filesystem::makeDirectory($path))
			{
				echo json_encode(array('error' => Lang::txt('COM_WIKI_ERROR_UNABLE_TO_CREATE_DIRECTORY')));
				return;
			}
		}

		if (!is_writable($path))
		{
			echo json_encode(array('error' => Lang::txt('COM_WIKI_ERROR_DIRECTORY_NOT_WRITABLE')));
			return;
		}

		// check to make sure we have a file and its not too big
		if ($size == 0)
		{
			echo json_encode(array('error' => Lang::txt('COM_WIKI_ERROR_NO_FILE')));
			return;
		}
		if ($size > $sizeLimit)
		{
			$max = preg_replace('/<abbr \w+=\\"\w+\\">(\w{1,3})<\\/abbr>/', '$1', Number::formatBytes($sizeLimit));
			echo json_encode(array('error' => Lang::txt('COM_WIKI_ERROR_FILE_TOO_LARGE', $max)));
			return;
		}

		// don't overwrite previous files that were uploaded
		$pathinfo = pathinfo($file);
		$filename = $pathinfo['filename'];

		// Make the filename safe
		$filename = urldecode($filename);
		$filename = Filesystem::clean($filename);
		$filename = str_replace(' ', '_', $filename);

		$ext = $pathinfo['extension'];
		while (file_exists($path . DS . $filename . '.' . $ext))
		{
			$filename .= rand(10, 99);
		}

		$file = $path . DS . $filename . '.' . $ext;

		if ($stream)
		{
			// read the php input stream to upload file
			$input = fopen("php://input", "r");
			$temp = tmpfile();
			$realSize = stream_copy_to_stream($input, $temp);
			fclose($input);

			// move from temp location to target location which is user folder
			$target = fopen($file , "w");
			fseek($temp, 0, SEEK_SET);
			stream_copy_to_stream($temp, $target);
			fclose($target);
		}
		else
		{
			move_uploaded_file($_FILES['qqfile']['tmp_name'], $file);
		}

		// Create database entry
		$attachment->pageid      = $listdir;
		$attachment->filename    = $filename . '.' . $ext;
		$attachment->description = trim(Request::getVar('description', '', 'post'));
		$attachment->created     = Date::toSql();
		$attachment->created_by  = User::get('id');

		if (!$attachment->check())
		{
			$this->setError($attachment->getError());
		}
		if (!$attachment->store())
		{
			$this->setError($attachment->getError());
		}

		//echo result
		echo json_encode(array(
			'success'   => true,
			'file'      => $filename . '.' . $ext,
			'directory' => str_replace(PATH_APP, '', $path)
		));
	}

	/**
	 * Upload a file to the wiki
	 *
	 * @return     void
	 */
	public function uploadTask()
	{
		// Check if they're logged in
		if (User::isGuest())
		{
			$this->displayTask();
			return;
		}

		if (Request::getVar('no_html', 0))
		{
			return $this->ajaxUploadTask();
		}

		// Ensure we have an ID to work with
		$listdir = Request::getInt('listdir', 0, 'post');
		if (!$listdir)
		{
			$this->setError(Lang::txt('COM_WIKI_NO_ID'));
			$this->displayTask();
			return;
		}

		// Incoming file
		$file = Request::getVar('upload', '', 'files', 'array');
		if (!$file['name'])
		{
			$this->setError(Lang::txt('COM_WIKI_NO_FILE'));
			$this->displayTask();
			return;
		}

		$attachment = new Tables\Attachment($this->database);

		// Build the upload path if it doesn't exist
		$path = $attachment->filespace() . DS . $listdir;

		if (!is_dir($path))
		{
			if (!Filesystem::makeDirectory($path))
			{
				$this->setError(Lang::txt('COM_WIKI_ERROR_UNABLE_TO_CREATE_DIRECTORY'));
				$this->displayTask();
				return;
			}
		}

		// Make the filename safe
		$file['name'] = urldecode($file['name']);
		$file['name'] = Filesystem::clean($file['name']);
		$file['name'] = str_replace(' ', '_', $file['name']);

		// Upload new files
		if (!Filesystem::upload($file['tmp_name'], $path . DS . $file['name']))
		{
			$this->setError(Lang::txt('COM_WIKI_ERROR_UPLOADING'));
		}
		// File was uploaded
		else
		{
			// Create database entry
			$attachment->pageid      = $listdir;
			$attachment->filename    = $file['name'];
			$attachment->description = trim(Request::getVar('description', '', 'post'));
			$attachment->created     = Date::toSql();
			$attachment->created_by  = User::get('id');

			if (!$attachment->check())
			{
				$this->setError($attachment->getError());
			}
			if (!$attachment->store())
			{
				$this->setError($attachment->getError());
			}
		}

		// Push through to the media view
		$this->displayTask();
	}

	/**
	 * Delete a folder in the wiki
	 *
	 * @return     void
	 */
	public function deletefolderTask()
	{
		// Check if they're logged in
		if (User::isGuest())
		{
			$this->displayTask();
			return;
		}

		// Incoming group ID
		$listdir = Request::getInt('listdir', 0, 'get');
		if (!$listdir)
		{
			$this->setError(Lang::txt('COM_WIKI_NO_ID'));
			$this->displayTask();
			return;
		}

		// Incoming folder
		$folder = trim(Request::getVar('folder', '', 'get'));
		if (!$folder)
		{
			$this->setError(Lang::txt('COM_WIKI_NO_DIRECTORY'));
			$this->displayTask();
			return;
		}

		$attachment = new Tables\Attachment($this->database);

		// Build the file path
		$path = $attachment->filespace() . DS . $listdir . DS . $folder;

		// Delete the folder
		if (is_dir($path))
		{
			// Attempt to delete the file
			if (!Filesystem::deleteDirectory($path))
			{
				$this->setError(Lang::txt('COM_WIKI_ERROR_UNABLE_TO_DELETE_DIRECTORY'));
			}
		}
		else
		{
			$this->setError(Lang::txt('COM_WIKI_NO_DIRECTORY'));
		}

		// Push through to the media view
		if (Request::getVar('no_html', 0))
		{
			return $this->listTask();
		}

		// Push through to the media view
		$this->displayTask();
	}

	/**
	 * Delete a file in the wiki
	 *
	 * @return     void
	 */
	public function deletefileTask()
	{
		// Check if they're logged in
		if (User::isGuest())
		{
			$this->displayTask();
			return;
		}

		// Incoming
		$listdir = Request::getInt('listdir', 0, 'get');
		if (!$listdir)
		{
			$this->setError(Lang::txt('COM_WIKI_NO_ID'));
			$this->displayTask();
			return;
		}

		// Incoming file
		$file = trim(Request::getVar('file', '', 'get'));
		if (!$file)
		{
			$this->setError(Lang::txt('COM_WIKI_NO_FILE'));
			$this->displayTask();
			return;
		}

		$attachment = new Tables\Attachment($this->database);

		// Build the file path
		$path = $attachment->filespace() . DS . $listdir;

		// Delete the file
		if (!file_exists($path . DS . $file) or !$file)
		{
			$this->setError(Lang::txt('COM_WIKI_ERROR_NO_FILE'));
			$this->displayTask();
		}
		else
		{
			// Attempt to delete the file
			if (!Filesystem::delete($path . DS . $file))
			{
				$this->setError(Lang::txt('COM_WIKI_ERROR_UNABLE_TO_DELETE_FILE', $file));
			}
			else
			{
				// Delete the database entry for the file
				$attachment->deleteFile($file, $listdir);
			}
		}

		// Push through to the media view
		if (Request::getVar('no_html', 0))
		{
			return $this->listTask();
		}

		$this->displayTask();
	}

	/**
	 * Display a form for uploading files
	 *
	 * @return     void
	 */
	public function displayTask()
	{
		foreach ($this->getErrors() as $error)
		{
			$this->view->setError($error);
		}

		$this->view
			->set('config', $this->config)
			->set('listdir', Request::getInt('listdir', 0, 'request'))
			->setLayout('display')
			->display();
	}

	/**
	 * Display a list of files
	 *
	 * @return     void
	 */
	public function listTask()
	{
		// Incoming
		$listdir = Request::getInt('listdir', 0, 'get');

		if (!$listdir)
		{
			$this->setError(Lang::txt('COM_WIKI_NO_ID'));
		}

		$attachment = new Tables\Attachment($this->database);

		$path = $attachment->filespace() . DS . $listdir;

		$folders = array();
		$docs    = array();

		if (is_dir($path))
		{
			// Loop through all files and separate them into arrays of images, folders, and other
			$dirIterator = new \DirectoryIterator($path);
			foreach ($dirIterator as $file)
			{
				if ($file->isDot())
				{
					continue;
				}

				if ($file->isDir())
				{
					$name = $file->getFilename();
					$folders[$path . DS . $name] = $name;
					continue;
				}

				if ($file->isFile())
				{
					$name = $file->getFilename();
					if (('cvs' == strtolower($name))
					 || ('.svn' == strtolower($name)))
					{
						continue;
					}

					$docs[$path . DS . $name] = $name;
				}
			}

			ksort($folders);
			ksort($docs);
		}

		$this->view->docs    = $docs;
		$this->view->folders = $folders;
		$this->view->config  = $this->config;
		$this->view->listdir = $listdir;
		$this->view->name    = $this->_name;
		$this->view->sub     = $this->_sub;

		foreach ($this->getErrors() as $error)
		{
			$this->view->setError($error);
		}

		$this->view
			->setLayout('list')
			->display();
	}
}

