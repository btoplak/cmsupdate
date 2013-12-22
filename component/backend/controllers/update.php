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
		$allowedTasks = array('browse', 'init', 'download', 'downloader', 'extract', 'finalise', 'htmaker');

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

			return true;
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

	public function extract()
	{
		if ($this->csrfProtection)
		{
			$this->_csrfProtection();
		}

		$model = $this->getThisModel();

		$backupOnUpdate = $model->backupOnUpdate;
		$hasAkeebaBackup = $model->hasAkeebaBackup();
		$takenBackup = $this->input->getInt('is_backed_up', 0);

		// Try to create a restoration.ini file
		if (!$takenBackup)
		{
			if (!$model->createRestorationINI())
			{
				$url = 'index.php?option=com_cmsupdate';
				$msg = JText::_('COM_CMSUPDATE_EXTRACT_ERR_CANWRITEINI');
				$this->setRedirect($url, $msg, 'error');

				return true;
			}
		}

		// Save the update_password in the session, we'll need it if this page is reloaded
		JFactory::getApplication()->setUserState($model->getHash() . 'update_password', $model->update_password);

		if ($backupOnUpdate && $hasAkeebaBackup && !$takenBackup)
		{
			// Backup the site
			$return_url = 'index.php?option=com_cmsupdate&task=extract&is_backed_up=1&' . JFactory::getSession()->getFormToken() . '=1';
			// @todo Allow the user to specify a backup profile
			$redirect_url = 'index.php?option=com_akeeba&view=backup&autostart=1&returnurl=' . urlencode($return_url);

			$this->setRedirect($redirect_url);

			return true;
		}

		return $this->display(false);
	}

	public function finalise()
	{
		// Do not add CSRF protection in this view; it called after the
		// installation of the update. At this point the session MAY have
		// already expired.

		$this->getThisModel()->finalize();
		$this->getThisModel()->purgeJoomlaUpdateCache();

		$this->setRedirect('index.php?option=com_cmsupdate&force=1');
	}

	public function htmaker()
	{
		$htMakerModel = FOFModel::getTmpInstance('Htmaker', 'AdmintoolsModel');
		$config = $htMakerModel->loadConfiguration();
		$config->exceptionfiles[] = 'administrator/components/com_cmsupdate/restore.php';
		$config->exceptionfiles = array_unique($config->exceptionfiles);
		$htMakerModel->saveConfiguration($config, true);
		$htMakerModel->makeHtaccess();

		$url = 'index.php?option=com_cmsupdate&task=extract&' . JFactory::getSession()->getFormToken() . '=1';
		$this->setRedirect($url);

		return true;
	}
} 