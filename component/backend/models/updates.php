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

class CmsupdateModelUpdates extends FOFModel
{
	/**
	 * Public constructor
	 *
	 * @param   array   $config  The model configuration array
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
			'enable'	=> $config->get('ftp_enable', 0),
			'host'		=> $config->get('ftp_host', 'localhost'),
			'port'		=> $config->get('ftp_port', '21'),
			'user'		=> $config->get('ftp_user', ''),
			'pass'		=> $config->get('ftp_pass', ''),
			'root'		=> $config->get('ftp_root', ''),
			'tempdir'	=> $config->get('tmp_path', ''),
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
	 * @param   boolean  $force  Should I forcibly reload the update information, refreshing the cache?
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

			$nextCheckTimeStamp = $lastCheck + 3600 * $frequency;

			$force = $nextCheckTimeStamp >= time();
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
				'lts'		=> true,
				'sts'		=> true,
				'test'		=> true,
				'custom'	=> $params->get('customurl', ''),
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
	 * @param   boolean  $force  Set to true to forcibly reload from the network
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
			'source'	=> 'none',
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

		$jVersion = $this->sanitiseVersion(JVERSION);

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
	 * Sets the downloadurl state variable based on the update section specified. The
	 * section can be lts, sts, current, installed or test. If that update section
	 * comes up empty we throw an exception.
	 *
	 * @param   string  $section  The update section we are using to download Joomla!
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

		$this->setState('downloadurl', $update['package']);
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
			throw new Exception(JText::sprintf('COM_CMSUPDATE_ERR_DOWNLOAD_INVALIDTMPDIR', $tmpDir) ,500);
		}

		// We will try to work around that anyway
		/**
		if (!is_writable($tmpDir))
		{
			throw new Exception(JText::_('COM_CMSUPDATE_ERR_DOWNLOAD_UNWRITEABLETMPDIR') ,500);
		}
		/**/

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

				if(version_compare(JVERSION,'3.0','ge'))
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
	 * @param   boolean  $staggered  Should I try a staggered (multi-step) download? Default is true.
	 *
	 * @return  array  A return array giving the status of the staggered download
	 */

	public function stepDownload($staggered = true)
	{
		$params = array(
			'file'		=> $this->getState('downloadurl', ''),
			'frag'		=> $this->getState('frag', -1),
			'totalSize'	=> $this->getState('totalSize', -1),
			'doneSize'	=> $this->getState('doneSize', -1),
		);

		if ($staggered)
		{
			$download = new AcuDownload();
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
				$result = $this->adapter->downloadAndReturn($params['file']);

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
	 * @param   integer  $l  Length of the random string, default 32 characters
	 * @param   string   $c  Character set to pick characters from
	 *
	 * @return  string  Your random string
	 */
	protected function getRandomString($l = 32, $c = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890')
	{
		for ($s = '', $cl = strlen($c)-1, $i = 0; $i < $l; $s .= $c[mt_rand(0, $cl)], ++$i);
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
		$data .= <<<ENDDATA
	'kickstart.security.password' => '$password',
	'kickstart.tuning.max_exec_time' => '5',
	'kickstart.tuning.run_time_bias' => '75',
	'kickstart.tuning.min_exec_time' => '0',
	'kickstart.procengine' => 'hybrid',
	'kickstart.setup.sourcefile' => 'joomla.zip',
	'kickstart.setup.destdir' => '$siteroot',
	'kickstart.setup.restoreperms' => '0',
	'kickstart.setup.filetype' => 'zip',
	'kickstart.setup.dryrun' => '0'
ENDDATA;

		$ftpOptions = $this->getFTPOptions();

		if ($ftpOptions['enable'])
		{
			// Get an instance of the FTP client
			JLoader::import('joomla.client.ftp');

			if(version_compare(JVERSION,'3.0','ge'))
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
}