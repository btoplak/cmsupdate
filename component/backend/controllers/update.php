<?php
/**
 *  @package    AkeebaCMSUpdate
 *  @copyright  Copyright (c)2010-2013 Nicholas K. Dionysopoulos
 *  @license    GNU General Public License version 3, or later
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

defined('_JEXEC') or die();

class CmsupdateControllerUpdate extends FOFController
{
	/**
	 * Executes a given controller task. The onBefore<task> and onAfter<task>
	 * methods are called automatically if they exist.
	 *
	 * @param   string $task The task to execute, e.g. "browse"
	 *
	 * @throws  Exception   Exception thrown if the onBefore<task> returns false
	 *
	 * @return  null|bool  False on execution failure
	 */
	public function execute($task)
	{
		$allowedTasks = array('browse', 'init', 'download', 'downloader', 'extract', 'finalise');

		if (!in_array($task, $allowedTasks))
		{
			$task = 'browse';
		}

		return parent::execute($task);
	}

	public function onBeforeBrowse()
	{
		$result = parent::onBeforeBrowse();

		if ($result)
		{
			$force = $this->input->getInt('force', 0);
			$this->getThisModel()->setState('force', $force);
		}

		return $result;
	}

	public function init()
	{
		// Apply the usual CSRF protection
		if ($this->csrfProtection)
		{
			$this->_csrfProtection();
		}

		// Get a reference to the model
		$model = $this->getThisModel();

		// Reset its saved state
		$model->resetSavedState();

		// Reading model state variables when the state is not set but the variables
		// exist in the request causes the model to read the variables from the request
		// and persist them in the session. So, whetever you do, DO NOT REMOVE THESE
		// UNUSED VARIABLES CODE FROM HERE!!!
		$dummy = $model->backupOnUpdate;
		$dummy = $model->user;
		$dummy = $model->pass;

		// Try to perform the initialisation
		try
		{
			// Tell the model which update section to use
			$model->setDownloadURLFromSection($this->input->getCmd('source', ''));
			// Prepare for downloading the update
			$model->prepareDownload();
		}
		catch (Exception $e)
		{
			// Oops, something went wrong
			$url = 'index.php?option=com_cmsupdate';
			$this->setRedirect($url, $e->getMessage(), 'error');

			return;
		}

		// Proceed to download
		$token = '';
		if (!FOFPlatform::getInstance()->isCli())
		{
			$token = '&' . JFactory::getSession()->getFormToken() . '=1';
		}

		$url = 'index.php?option=com_cmsupdate&view=update&task=download' . $token;
		$this->setRedirect($url);
	}

	public function download()
	{
		if ($this->csrfProtection)
		{
			$this->_csrfProtection();
		}

		return $this->display(false);
	}

	public function downloader()
	{
		$json = $this->input->get('json', '', 'raw');
		$params = json_decode($json, true);
		$model = $this->getThisModel();
		if (is_array($params) && !empty($params))
		{
			foreach ($params as $k => $v)
			{
				$model->setState($k, $v);
			}
		}

		$ret = $model->stepDownload();

		echo '###' . json_encode($ret) . '###';

		return true;
	}
} 