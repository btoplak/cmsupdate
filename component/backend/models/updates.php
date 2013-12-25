<?php
/**
 * @package    AkeebaCMSUpdate
 * @copyright  Copyright (c)2010-2013 Nicholas K. Dionysopoulos
 * @license    GNU General Public License version 3, or later
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

class CmsupdateModelUpdates extends FOFModel
{
	/**
	 * Public constructor
	 *
	 * @param   array $config The model configuration array
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		$this->table = '';
	}

	/**
	 * Purges the Joomla! update cache. We ARE NOT using this cache, but the CMS
	 * does. We want to bust the cache to provent Joomla! from reporting updates
	 * after we install an update through our component
	 *
	 * @return  bool  True on success
	 */
	protected function purgeJoomlaUpdateCache()
	{
		$db = JFactory::getDbo();

		// Modify the database record
		$update_site = new stdClass;
		$update_site->last_check_timestamp = 0;
		$update_site->enabled = 1;
		$update_site->update_site_id = 1;
		$db->updateObject('#__update_sites', $update_site, 'update_site_id');

		$query = $db->getQuery(true)
			->delete($db->quoteName('#__updates'))
			->where($db->quoteName('update_site_id') . ' = ' . $db->quote('1'));
		$db->setQuery($query);

		if ($db->execute())
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Returns an array with the configured FTP options
	 *
	 * @return  array
	 */
	public function getFTPOptions()
	{
		// Initialise from Joomla! Global Configuration
		$config = JFactory::getConfig();
		$retArray = array(
			'enable'  => $config->get('ftp_enable', 0),
			'host'    => $config->get('ftp_host', 'localhost'),
			'port'    => $config->get('ftp_port', '21'),
			'user'    => $config->get('ftp_user', ''),
			'pass'    => $config->get('ftp_pass', ''),
			'root'    => $config->get('ftp_root', ''),
			'tempdir' => $config->get('tmp_path', ''),
		);

		// Get the username and password from the state variables, if it exists
		$stateUser = $this->getState('user', '', 'raw');
		$statePass = $this->getState('pass', '', 'raw');

		if (!empty($stateUser))
		{
			$retArray['user'] = $stateUser;
		}

		if (!empty($statePass))
		{
			$retArray['pass'] = $statePass;
		}

		// Apply the FTP credentials to Joomla! itself
		JLoader::import('joomla.client.helper');
		JClientHelper::setCredentials('ftp', $retArray['user'], $retArray['pass']);

		return $retArray;
	}

	/**
	 * Returns the (cached) list of updates for every section: installed version, current
	 * branch updates, sts/lts updates, testing updates.
	 *
	 * @param   boolean $force Should I forcibly reload the update information, refreshing the cache?
	 *
	 * @return  array|null  The updates array, null if crap hits the fan
	 */
	public function getAllUpdates($force = false)
	{
		// Get the component parameters
		JLoader::import('cms.component.helper');
		$params = JComponentHelper::getParams('com_cmsupdate');

		// Do I have to check for updates?
		if (!$force)
		{
			// Check with the specified frequency which has to be between 1 hour and 30 days
			$frequency = $params->get('frequency', 6);

			if (($frequency < 0) || ($frequency > 720))
			{
				$frequency = 6;
			}

			// Get the last time we checked for updates
			$lastCheck = $params->get('lastcheck', 0);
			$this->setState('lastCheck', $lastCheck);

			$nextCheckTimeStamp = $lastCheck + 3600 * $frequency;

			$force = $nextCheckTimeStamp <= time();
		}

		// Do I have a cache? If not I have to force an update fetch.
		$cache = null;

		if (!$force)
		{
			$cacheEncoded = $params->get('updatecache', '');

			if (!empty($cacheEncoded))
			{
				$cache = json_decode($cacheEncoded, true);
			}

			if (empty($cache))
			{
				$cache = null;
				$force = true;
			}
		}

		// If we are forced to perform an update fetch do it and refresh the cache
		if ($force)
		{
			// Get the update sources we are configured to use
			$sources = array(
				'lts'    => true,
				'sts'    => true,
				'test'   => true,
				'custom' => $params->get('customurl', ''),
			);

			switch ($params->get('updatesource', 'all'))
			{
				case 'custom':
					$sources['lts'] = false;
					$sources['sts'] = false;
					$sources['testing'] = false;
					break;

				case 'testing':
					$sources['lts'] = false;
					$sources['sts'] = false;
					$sources['custom'] = '';
					break;

				case 'sts':
					$sources['lts'] = false;
					$sources['test'] = false;
					$sources['custom'] = '';
					break;

				case 'lts':
					$sources['sts'] = false;
					$sources['test'] = false;
					$sources['custom'] = '';
					break;
			}

			// Get the updates
			$provider = new AcuUpdateProviderJoomla();
			$cache = $provider->getUpdates($sources);

			// JSON-encode them
			$cacheEncoded = json_encode($cache);

			// Save the cache
			$params->set('updatecache', $cacheEncoded);
			$params->set('lastcheck', time());
			$this->setState('lastCheck', time());

			$component = JComponentHelper::getComponent('com_cmsupdate');

			$db = JFactory::getDbo();
			$query = $db->getQuery(true)
				->update($db->qn('#__extensions'))
				->set($db->qn('params') . ' = ' . $db->q($params->toString('JSON')))
				->where($db->qn('extension_id') . ' = ' . $db->q($component->id));
			$db->setQuery($query);
			$db->execute();
		}

		return $cache;
	}

	/**
	 * Returns information about whether we need to update Joomla!
	 *
	 * @param   boolean $force Set to true to forcibly reload from the network
	 *
	 * @return  object
	 */
	public function getUpdateInfo($force = false)
	{
		static $updateInfo = null;

		if (!empty($updateInfo) && !$force)
		{
			return $updateInfo;
		}

		$updateInfo = array(
			'status'    => false,
			'source'    => 'none',
			'installed' => null,
			'current'   => null,
			'sts'       => null,
			'lts'       => null,
			'test'      => null,
		);

		$data = $this->getAllUpdates($force);

		if (empty($data))
		{
			return (object)$updateInfo;
		}

		$updateInfo = (object)array_merge($updateInfo, $data);

		// Get the minnotify setting
		$params = JComponentHelper::getParams('com_cmsupdate');
		$minnotify = $params->get('minnotify', 'current');

		$provider = new AcuUpdateProviderJoomla();
		$jVersion = $provider->sanitiseVersion(JVERSION);

		// We trigger an update only when there is a new release of the minimum specified stability available for download
		switch ($minnotify)
		{
			case 'test':
				// Do we have a testing release?
				if (!empty($updateInfo->test['version']) && ($updateInfo->test['version'] != $jVersion))
				{
					$updateInfo->status = true;
					$updateInfo->source = 'test';
					break;
				}
			// Do not break; we have to fall through the rest of the switch

			case 'lts':
				// Do we have an lts release?
				if (!empty($updateInfo->lts['version']) && ($updateInfo->lts['version'] != $jVersion))
				{
					$updateInfo->status = true;
					$updateInfo->source = 'lts';
					break;
				}
			// Do not break; we have to fall through the rest of the switch

			case 'sts':
				// Do we have an sts release?
				if (!empty($updateInfo->sts['version']) && ($updateInfo->sts['version'] != $jVersion))
				{
					$updateInfo->status = true;
					$updateInfo->source = 'sts';
					break;
				}
			// Do not break; we have to fall through the rest of the switch

			case 'current':
				// Do we have a current branch release?
				if (!empty($updateInfo->current['version']) && ($updateInfo->current['version'] != $jVersion))
				{
					$updateInfo->status = true;
					$updateInfo->source = 'current';
					break;
				}
				break;
		}

		return $updateInfo;
	}

	/**
	 * Checks if the site has Akeeba Backup 3.1 or later installed
	 *
	 * @return  boolean  True if Akeeba Backup is installed and enabled
	 */
	public function hasAkeebaBackup()
	{
		// Is the component installed, at all?
		JLoader::import('joomla.filesystem.folder');

		if (!JFolder::exists(JPATH_ADMINISTRATOR . '/components/com_akeeba'))
		{
			return false;
		}

		// Make sure the component is enabled
		JLoader::import('cms.component.helper');
		$component = JComponentHelper::getComponent('com_akeeba', true);

		if (!$component->enabled)
		{
			return false;
		}

		return true;
	}

	/**
	 * Should we attempt to backup on update?
	 *
	 * @return  boolean  True if we do have to backup on update
	 */
	public function hasBackupOnUpdate()
	{
		if (!$this->hasAkeebaBackup())
		{
			return false;
		}

		JLoader::import('cms.component.helper');
		$params = JComponentHelper::getParams('com_cmsupdate');

		return $params->get('backuponupdate', 1);
	}

	public function getBackupProfile()
	{
		JLoader::import('cms.component.helper');
		$params = JComponentHelper::getParams('com_cmsupdate');

		return $params->get('backupprofile', 1);
	}

	/**
	 * Checks if the site has Admin Tools installed
	 *
	 * @return  boolean  True if Admin Tools is installed and enabled
	 */
	public function hasAdminTools()
	{
		// Is the component installed, at all?
		JLoader::import('joomla.filesystem.folder');

		if (!JFolder::exists(JPATH_ADMINISTRATOR . '/components/com_admintools'))
		{
			return false;
		}

		// Make sure the component is enabled
		JLoader::import('cms.component.helper');
		$component = JComponentHelper::getComponent('com_admintools', true);

		if (!$component->enabled)
		{
			return false;
		}

		return true;
	}

	/**
	 * Sets the downloadurl state variable based on the update section specified. The
	 * section can be lts, sts, current, installed or test. If that update section
	 * comes up empty we throw an exception.
	 *
	 * @param   string $section The update section we are using to download Joomla!
	 *
	 * @throws  Exception
	 */
	public function setDownloadURLFromSection($section)
	{
		$allUpdates = $this->getAllUpdates();

		if (!array_key_exists($section, $allUpdates))
		{
			throw new Exception(JText::sprintf('COM_CMSUPDATE_ERR_DOWNLOAD_NOSUCHSECTION', $section), 500);
		}

		$update = $allUpdates[$section];

		if (empty($update['package']))
		{
			throw new Exception(JText::sprintf('COM_CMSUPDATE_ERR_DOWNLOAD_NOUPDATESINSECTION', $section), 500);
		}

		// This trick forces the downloadurl variable to persist in the session
		if (!FOFPlatform::getInstance()->isCli())
		{
			JFactory::getApplication()->setUserState($this->getHash() . 'downloadurl', $update['package']);
			$dummy = $this->downloadurl;
		}
		else
		{
			$this->downloadurl = $update['package'];
		}
	}

	/**
	 * Try to prepare a world-writeable joomla.zip file in the site's temporary directory,
	 * or throw an exception if it's not possible
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 */
	public function prepareDownload()
	{
		JLoader::import('joomla.filesystem.file');
		JLoader::import('joomla.filesystem.folder');

		$tmpDir = JFactory::getConfig()->get('tmp_path');
		$tmpFile = rtrim($tmpDir, '/\\') . '/joomla.zip';

		if (!is_dir($tmpDir))
		{
			throw new Exception(JText::sprintf('COM_CMSUPDATE_ERR_DOWNLOAD_INVALIDTMPDIR', $tmpDir), 500);
		}

		// We will try to work around that anyway
		/**
		 * if (!is_writable($tmpDir))
		 * {
		 * throw new Exception(JText::_('COM_CMSUPDATE_ERR_DOWNLOAD_UNWRITEABLETMPDIR') ,500);
		 * }
		 * /**/

		if (file_exists($tmpFile))
		{
			if (!@unlink($tmpFile))
			{
				JFile::delete($tmpFile);
			}
		}

		if (file_exists($tmpFile))
		{
			throw new Exception(JText::sprintf('COM_CMSUPDATE_ERR_DOWNLOAD_CANTREMOVEOLDFILE', $tmpDir), 500);
		}

		$fp = @fopen($tmpFile, 'wb');
		if ($fp === false)
		{
			$nada = '';
			JFile::write($tmpFile, $nada);
		}
		else
		{
			fclose($fp);
		}

		$result = @chmod($tmpFile, 0777);

		// If we can't chmod directly let's try using FTP
		if (!$result)
		{
			$ftpOptions = $this->getFTPOptions();

			if ($ftpOptions['enable'])
			{
				JLoader::import('joomla.client.ftp');

				if (version_compare(JVERSION, '3.0', 'ge'))
				{
					$ftp = JClientFTP::getInstance(
						$ftpOptions['host'], $ftpOptions['port'], array('type' => FTP_BINARY),
						$ftpOptions['user'], $ftpOptions['pass']
					);
				}
				else
				{
					$ftp = JFTP::getInstance(
						$ftpOptions['host'], $ftpOptions['port'], array('type' => FTP_BINARY),
						$ftpOptions['user'], $ftpOptions['pass']
					);
				}

				$path = JPath::clean(str_replace(JPATH_ROOT, $ftpOptions['root'], $tmpFile), '/');
				$result = $ftp->chmod($path, 0777);
			}
		}

		if (!$result)
		{
			throw new Exception(JText::_('COM_CMSUPDATE_ERR_DOWNLOAD_CANTCREATEWRITEABLEFILE'), 500);
		}
	}

	/**
	 * Step through the download. Remember to set the URL in the downloadurl state variable
	 * e.g. by using setDownloadURLFromSection
	 *
	 * @param   boolean $staggered Should I try a staggered (multi-step) download? Default is true.
	 *
	 * @return  array  A return array giving the status of the staggered download
	 */

	public function stepDownload($staggered = true)
	{
		$params = array(
			'file'      => $this->getState('downloadurl', ''),
			'frag'      => $this->getState('frag', -1),
			'totalSize' => $this->getState('totalSize', -1),
			'doneSize'  => $this->getState('doneSize', -1),
		);

		$download = new AcuDownload();

		if ($staggered)
		{
			$retArray = $download->importFromURL($params);
		}
		else
		{
			$retArray = array(
				"status"    => true,
				"error"     => '',
				"frag"      => 1,
				"totalSize" => 0,
				"doneSize"  => 0,
				"percent"   => 0,
			);

			try
			{
				$result = $download->getFromURL($params['file']);

				if ($result === false)
				{
					throw new Exception(JText::sprintf('COM_CMSUPDATE_ERR_LIB_COULDNOTDOWNLOADFROMURL', $params['file']), 500);
				}

				$tmpDir = JFactory::getConfig()->get('tmp_path', JPATH_ROOT . '/tmp');
				$tmpDir = rtrim($tmpDir, '/\\');
				$localFilename = $tmpDir . '/joomla.zip';

				$status = file_put_contents($localFilename, $result);

				if (!$status)
				{
					JLoader::import('joomla.filesystem.file');
					$status = JFile::write($localFilename, $result);
				}

				if (!$status)
				{
					throw new Exception(JText::sprintf('COM_CMSUPDATE_ERR_LIB_COULDNOTWRITETOFILE', $localFilename), 500);
				}

				$retArray['status'] = true;
				$retArray['totalSize'] = strlen($result);
				$retArray['doneSize'] = $retArray['totalSize'];
				$retArray['percent'] = 100;
			}
			catch (Exception $e)
			{
				$retArray['status'] = true;
				$retArray['error'] = $e->getMessage();
			}
		}

		return $retArray;
	}

	/**
	 * Create a (semi-)random string
	 *
	 * @param   integer $l Length of the random string, default 32 characters
	 * @param   string  $c Character set to pick characters from
	 *
	 * @return  string  Your random string
	 */
	protected function getRandomString($l = 32, $c = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890')
	{
		for ($s = '', $cl = strlen($c) - 1, $i = 0; $i < $l; $s .= $c[mt_rand(0, $cl)], ++$i)
		{
			;
		}

		return $s;
	}

	public function createRestorationINI()
	{
		// Get a password
		$password = $this->getRandomString(64);

		$this->setState('update_password', $password);

		// Get the absolute path to site's root
		$siteroot = JPATH_SITE;
		$siteroot = str_replace('\\', '/', $siteroot);

		$jreg = JFactory::getConfig();
		$tempdir = $jreg->get('tmp_path');
		$file = $tempdir . '/joomla.zip';

		$data = "<?php\ndefined('_AKEEBA_RESTORATION') or die();\n";
		$data .= '$restoration_setup = array(' . "\n";

		$ftpOptions = $this->getFTPOptions();
		$engine = $ftpOptions['enable'] ? 'hybrid' : 'direct';

		$data .= <<<ENDDATA
	'kickstart.security.password' => '$password',
	'kickstart.tuning.max_exec_time' => '5',
	'kickstart.tuning.run_time_bias' => '75',
	'kickstart.tuning.min_exec_time' => '0',
	'kickstart.procengine' => '$engine',
	'kickstart.setup.sourcefile' => '{$tempdir}/joomla.zip',
	'kickstart.setup.destdir' => '$siteroot',
	'kickstart.setup.restoreperms' => '0',
	'kickstart.setup.filetype' => 'zip',
	'kickstart.setup.dryrun' => '0'
ENDDATA;

		if ($ftpOptions['enable'])
		{
			// Get an instance of the FTP client
			JLoader::import('joomla.client.ftp');

			if (version_compare(JVERSION, '3.0', 'ge'))
			{
				$ftp = JClientFTP::getInstance(
					$ftpOptions['host'], $ftpOptions['port'], array('type' => FTP_BINARY),
					$ftpOptions['user'], $ftpOptions['pass']
				);
			}
			else
			{
				$ftp = JFTP::getInstance(
					$ftpOptions['host'], $ftpOptions['port'], array('type' => FTP_BINARY),
					$ftpOptions['user'], $ftpOptions['pass']
				);
			}

			// Is the tempdir really writable?
			$writable = @is_writeable($tempdir);

			if ($writable)
			{
				// Let's be REALLY sure
				$fp = @fopen($tempdir . '/test.txt', 'w');
				if ($fp === false)
				{
					$writable = false;
				}
				else
				{
					fclose($fp);
					unlink($tempdir . '/test.txt');
				}
			}

			// If the tempdir is not writable, create a new writable subdirectory
			if (!$writable)
			{
				JLoader::import('joomla.filesystem.folder');

				$dest = JPath::clean(str_replace(JPATH_ROOT, $ftpOptions['root'], $tempdir . '/cmsupdate'), '/');

				if (!@mkdir($tempdir . '/cmsupdate'))
				{
					$ftp->mkdir($dest);
				}

				if (!@chmod($tempdir . '/cmsupdate', 511))
				{
					$ftp->chmod($dest, 511);
				}

				$tempdir .= '/cmsupdate';
			}

			// Just in case the temp-directory was off-root, try using the default tmp directory
			$writable = @is_writeable($tempdir);

			if (!$writable)
			{
				$tempdir = JPATH_ROOT . '/tmp';

				// Does the JPATH_ROOT/tmp directory exist?
				if (!is_dir($tempdir))
				{
					$htAccessContents = "order deny,allow\ndeny from all\nallow from none\n";
					JLoader::import('joomla.filesystem.file');
					JFolder::create($tempdir, 511);
					JFile::write($tempdir . '/.htaccess', $htAccessContents);
				}

				// If it exists and it is unwritable, try creating a writable cmsupdate subdirectory
				if (!is_writable($tempdir))
				{
					JLoader::import('joomla.filesystem.folder');

					$dest = JPath::clean(str_replace(JPATH_ROOT, $ftpOptions['root'], $tempdir . '/cmsupdate'), '/');
					if (!@mkdir($tempdir . '/cmsupdate'))
					{
						$ftp->mkdir($dest);
					}
					if (!@chmod($tempdir . '/cmsupdate', 511))
					{
						$ftp->chmod($dest, 511);
					}

					$tempdir .= '/cmsupdate';
				}
			}

			// If we still have no writable directory, we'll try /tmp and the system's temp-directory
			$writable = @is_writeable($tempdir);

			if (!$writable)
			{
				if (@is_dir('/tmp') && @is_writable('/tmp'))
				{
					$tempdir = '/tmp';
				}
				else
				{
					// Try to find the system temp path
					$tmpfile = @tempnam("dummy", "");
					$systemp = @dirname($tmpfile);
					@unlink($tmpfile);

					if (!empty($systemp))
					{
						if (@is_dir($systemp) && @is_writable($systemp))
						{
							$tempdir = $systemp;
						}
					}
				}
			}

			$data .= <<<ENDDATA
	,
	'kickstart.ftp.ssl' => '0',
	'kickstart.ftp.passive' => '1',
	'kickstart.ftp.host' => '{$ftpOptions['host']}',
	'kickstart.ftp.port' => '{$ftpOptions['port']}',
	'kickstart.ftp.user' => '{$ftpOptions['user']}',
	'kickstart.ftp.pass' => '{$ftpOptions['pass']}',
	'kickstart.ftp.dir' => '{$ftpOptions['root']}',
	'kickstart.ftp.tempdir' => '$tempdir'
ENDDATA;
		}

		$data .= ');';

		// Remove the old file, if it's there...
		JLoader::import('joomla.filesystem.file');

		$componentPaths = FOFPlatform::getInstance()->getComponentBaseDirs('com_cmsupdate');

		$configpath = $componentPaths['admin'] . '/restoration.php';

		if (file_exists($configpath))
		{
			if (!@unlink($configpath))
			{
				JFile::delete($configpath);
			}
		}

		// Write the new file. First try directly.
		if (function_exists('file_put_contents'))
		{
			$result = @file_put_contents($configpath, $data);
			if ($result !== false)
			{
				$result = true;
			}
		}
		else
		{
			$fp = @fopen($configpath, 'wt');
			if ($fp !== false)
			{
				$result = @fwrite($fp, $data);
				if ($result !== false)
				{
					$result = true;
				}
				@fclose($fp);
			}
		}

		if ($result === false)
		{
			$result = JFile::write($configpath, $data);
		}

		return $result;
	}

	/**
	 * Post-update clean up
	 *
	 * @param   boolean  $runUpdateScripts  Should I run the update scripts? Default: true
	 *
	 * @return  void
	 */
	public function finalize($runUpdateScripts = true)
	{
		JLoader::import('joomla.filesystem.file');
		JLoader::import('joomla.filesystem.folder');

		// Where is our temp directory?
		$jreg = JFactory::getConfig();
		$tempdir = $jreg->get('tmp_path');

		$file = rtrim($tempdir, '/\\') . '/joomla.zip';

		// Remove the update file
		if (file_exists($file))
		{
			if (!@unlink($tempdir . '/' . $file))
			{
				JFile::delete($tempdir . '/' . $file);
			}
		}

		// Remove the restoration.php file
		JLoader::import('joomla.filesystem.file');

		$componentPaths = FOFPlatform::getInstance()->getComponentBaseDirs('com_cmsupdate');

		$configpath = $componentPaths['admin'] . '/restoration.php';

		if (file_exists($configpath))
		{
			if (!@unlink($configpath))
			{
				JFile::delete($configpath);
			}
		}

		// Delete the temp-dir we may have created
		if (is_dir($tempdir . '/cmsupdate'))
		{
			$this->recursive_remove_directory($tempdir . '/cmsupdate');
		}

		if ($runUpdateScripts)
		{
			$this->runUpdateScripts();
		}
	}

	/**
	 * Recursively remove a directory and its contents
	 *
	 * @param   string  $directory  The directory to remove
	 *
	 * @return  boolean  True on success
	 */
	private function recursive_remove_directory($directory)
	{
		JLoader::import('joomla.filesystem.folder');

		// if the path has a slash at the end we remove it here
		if (substr($directory, -1) == '/')
		{
			$directory = substr($directory, 0, -1);
		}

		if (!file_exists($directory) || !is_dir($directory))
		{
			return false;
		}
		elseif (!is_readable($directory))
		{
			return false;
		}
		else
		{
			$handle = opendir($directory);

			while (($item = readdir($handle)) !== false)
			{
				if ($item != '.' && $item != '..')
				{
					$path = $directory . '/' . $item;

					if (is_dir($path))
					{

						$this->recursive_remove_directory($path);
					}
					else
					{
						if (!@unlink($path))
						{
							JFolder::delete($path);
						}
					}
				}
			}

			closedir($handle);

			$status = @rmdir($directory);

			if ($status === false)
			{
				$status = JFolder::delete($directory);
			}

			return $status;
		}
	}

	/**
	 * Executes the post-update scripts
	 *
	 * @return  boolean  True on success
	 */
	public function runUpdateScripts()
	{
		JLoader::import('joomla.installer.install');
		$installer = JInstaller::getInstance();

		$installer->setPath('source', JPATH_ROOT);
		$installer->setPath('extension_root', JPATH_ROOT);

		if (!$installer->setupInstall())
		{
			$installer->abort(JText::_('JLIB_INSTALLER_ABORT_DETECTMANIFEST'));

			return false;
		}

		$installer->extension = JTable::getInstance('extension');
		$installer->extension->load(700);
		$installer->setAdapter($installer->extension->type);

		$manifest = $installer->getManifest();

		$manifestPath = JPath::clean($installer->getPath('manifest'));
		$element = preg_replace('/\.xml/', '', basename($manifestPath));

		// Run the script file
		$scriptElement = $manifest->scriptfile;
		$manifestScript = (string)$manifest->scriptfile;

		if ($manifestScript)
		{
			$manifestScriptFile = JPATH_ROOT . '/' . $manifestScript;

			if (is_file($manifestScriptFile))
			{
				// load the file
				include_once $manifestScriptFile;
			}

			$classname = 'JoomlaInstallerScript';

			if (class_exists($classname))
			{
				$manifestClass = new $classname($this);
			}
		}

		ob_start();
		ob_implicit_flush(false);

		if ($manifestClass && method_exists($manifestClass, 'preflight'))
		{
			if ($manifestClass->preflight('update', $this) === false)
			{
				$installer->abort(JText::_('JLIB_INSTALLER_ABORT_FILE_INSTALL_CUSTOM_INSTALL_FAILURE'));

				return false;
			}
		}

		$msg = ob_get_contents(); // create msg object; first use here
		ob_end_clean();

		// Get a database connector object
		$db = JFactory::getDbo();

		// Check to see if a file extension by the same name is already installed
		// If it is, then update the table because if the files aren't there
		// we can assume that it was (badly) uninstalled
		// If it isn't, add an entry to extensions
		$query = $db->getQuery(true);
		$query->select($query->qn('extension_id'))
			->from($query->qn('#__extensions'));
		$query->where($query->qn('type') . ' = ' . $query->q('file'))
			->where($query->qn('element') . ' = ' . $query->q('joomla'));
		$db->setQuery($query);

		try
		{
			$db->execute();
		}
		catch (Exception $e)
		{
			// Install failed, roll back changes
			$installer->abort(
				JText::sprintf('JLIB_INSTALLER_ABORT_FILE_ROLLBACK', JText::_('JLIB_INSTALLER_UPDATE'), $db->stderr(true))
			);

			return false;
		}

		$id = $db->loadResult();
		$row = JTable::getInstance('extension');

		if ($id)
		{
			// Load the entry and update the manifest_cache
			$row->load($id);
			// Update name
			$row->set('name', 'files_joomla');
			// Update manifest
			$row->manifest_cache = $installer->generateManifestCache();

			if (!$row->store())
			{
				// Install failed, roll back changes
				$installer->abort(
					JText::sprintf('JLIB_INSTALLER_ABORT_FILE_ROLLBACK', JText::_('JLIB_INSTALLER_UPDATE'), $db->stderr(true))
				);

				return false;
			}
		}
		else
		{
			// Add an entry to the extension table with a whole heap of defaults
			$row->set('name', 'files_joomla');
			$row->set('type', 'file');
			$row->set('element', 'joomla');
			// There is no folder for files so leave it blank
			$row->set('folder', '');
			$row->set('enabled', 1);
			$row->set('protected', 0);
			$row->set('access', 0);
			$row->set('client_id', 0);
			$row->set('params', '');
			$row->set('system_data', '');
			$row->set('manifest_cache', $installer->generateManifestCache());

			if (!$row->store())
			{
				// Install failed, roll back changes
				$installer->abort(JText::sprintf('JLIB_INSTALLER_ABORT_FILE_INSTALL_ROLLBACK', $db->stderr(true)));

				return false;
			}

			// Set the insert id
			$row->set('extension_id', $db->insertid());

			// Since we have created a module item, we add it to the installation step stack
			// so that if we have to rollback the changes we can undo it.
			$installer->pushStep(array('type' => 'extension', 'extension_id' => $row->extension_id));
		}

		/*
		 * Let's run the queries for the file
		 */
		if ($manifest->update)
		{
			$result = $installer->parseSchemaUpdates($manifest->update->schemas, $row->extension_id);
			if ($result === false)
			{
				// Install failed, rollback changes
				$installer->abort(JText::sprintf('JLIB_INSTALLER_ABORT_FILE_UPDATE_SQL_ERROR', $db->stderr(true)));

				return false;
			}
		}

		// Start Joomla! 1.6
		ob_start();
		ob_implicit_flush(false);

		if ($manifestClass && method_exists($manifestClass, 'update'))
		{
			if ($manifestClass->update($installer) === false)
			{
				// Install failed, rollback changes
				$installer->abort(JText::_('JLIB_INSTALLER_ABORT_FILE_INSTALL_CUSTOM_INSTALL_FAILURE'));

				return false;
			}
		}

		$msg .= ob_get_contents(); // append messages
		ob_end_clean();

		// Lastly, we will copy the manifest file to its appropriate place.
		$manifest = array();
		$manifest['src'] = $installer->getPath('manifest');
		$manifest['dest'] = JPATH_MANIFESTS . '/files/' . basename($installer->getPath('manifest'));

		if (!$installer->copyFiles(array($manifest), true))
		{
			// Install failed, rollback changes
			$installer->abort(JText::_('JLIB_INSTALLER_ABORT_FILE_INSTALL_COPY_SETUP'));

			return false;
		}

		// Clobber any possible pending updates
		$update = JTable::getInstance('update');
		$uid = $update->find(
			array('element' => $element, 'type' => 'file', 'client_id' => '', 'folder' => '')
		);

		if ($uid)
		{
			$update->delete($uid);
		}

		// And now we run the postflight
		ob_start();
		ob_implicit_flush(false);

		if ($manifestClass && method_exists($manifestClass, 'postflight'))
		{
			$manifestClass->postflight('update', $this);
		}

		$msg .= ob_get_contents(); // append messages
		ob_end_clean();

		if ($msg != '')
		{
			$installer->set('extension_message', $msg);
		}

		return true;
	}

	/**
	 * Returns a fancy formatted time lapse code
	 *
	 * @param   integer  $referencedate	 Timestamp of the reference date/time
	 * @param   integer  $timepointer	 Timestamp of the current date/time
	 * @param   string   $measureby		 One of s, m, h, d, or y (time unit)
	 * @param   boolean  $autotext		 X
	 *
	 * @return  string
	 */
	private function timeago($referencedate = 0, $timepointer = 0, $measureby = '', $autotext = true)
	{
		if (empty($timepointer))
		{
			$timepointer = time();
		}

		// Raw time difference
		$Raw	 = $timepointer - $referencedate;
		$Clean	 = abs($Raw);

		if (($Raw >= 0) && ($Raw < 10))
		{
			return JText::_('COM_CMSUPDATE_UPDATES_LBL_TIMEAGO_JUSTNOW');
		}

		$calcNum = array(
			array('s', 60),
			array('m', 60 * 60),
			array('h', 60 * 60 * 60),
			array('d', 60 * 60 * 60 * 24),
			array('y', 60 * 60 * 60 * 24 * 365)
		);

		$calc = array(
			's'	 => array(1, 'second'),
			'm'	 => array(60, 'minute'),
			'h'	 => array(60 * 60, 'hour'),
			'd'	 => array(60 * 60 * 24, 'day'),
			'y'	 => array(60 * 60 * 24 * 365, 'year')
		);

		if ($measureby == '')
		{
			$usemeasure = 's';

			for ($i = 0; $i < count($calcNum); $i++)
			{
				if ($Clean <= $calcNum[$i][1])
				{
					$usemeasure	 = $calcNum[$i][0];
					$i			 = count($calcNum);
				}
			}
		}
		else
		{
			$usemeasure = $measureby;
		}

		$datedifference = floor($Clean / $calc[$usemeasure][0]);

		if ($autotext == true && ($timepointer == time()))
		{
			if ($Raw < 0)
			{
				$prospect = 'fromnow';
			}
			else
			{
				$prospect = 'ago';
			}
		}
		else
		{
			$prospect = '';
		}

		if ($referencedate != 0)
		{
			if ($datedifference == 1)
			{
				return $datedifference . ' ' . JText::_('COM_CMSUPDATE_UPDATES_LBL_TIMEAGO_UNIT_' . $calc[$usemeasure][1])  . ' ' . JText::_('COM_CMSUPDATE_UPDATES_LBL_TIMEAGO_PROSPECT_' . $prospect);
			}
			else
			{
				return $datedifference . ' ' . JText::_('COM_CMSUPDATE_UPDATES_LBL_TIMEAGO_UNIT_' . $calc[$usemeasure][1] . 's')  . ' ' . JText::_('COM_CMSUPDATE_UPDATES_LBL_TIMEAGO_PROSPECT_' . $prospect);
			}
		}
		else
		{
			return JText::_('COM_CMSUPDATE_UPDATES_LBL_TIMEAGO_NOREF');
		}
	}

	/**
	 * Returns a human readable, slightly fuzzy string on how long ago the
	 * update check took place, e.g. "just now", "10 minutes ago", "1 year
	 * ago".
	 *
	 * @return  string  The human readable string on how long ago the update check took place
	 */
	public function getHumanReadableLastCheck()
	{
		$lastCheck = $this->getState('lastCheck', 0);

		if (empty($lastCheck))
		{
			JLoader::import('cms.component.helper');
			$params = JComponentHelper::getParams('com_cmsupdate');
			$lastCheck = $params->get('lastcheck', 0);
		}

		return $this->timeago($lastCheck);
	}
}