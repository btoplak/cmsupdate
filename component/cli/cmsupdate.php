<?php
/**
 *  @package    AkeebaCMSUpdate
 *  @copyright  Copyright (c)2010-2014 Nicholas K. Dionysopoulos
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

// Required by the CMS
define('DS', DIRECTORY_SEPARATOR);

// Required by restore.php to let us handle extraction manually
define('KICKSTART', 1);

// Enable Akeeba Engine, required for the backup on update feature
define('AKEEBAENGINE', 1);

// Required to include Joomla! core files
define('_JEXEC', 1);

$minphp = '5.3.1';
if (version_compare(PHP_VERSION, $minphp, 'lt'))
{
	$curversion = PHP_VERSION;
	$bindir = PHP_BINDIR;
	echo <<< ENDWARNING
================================================================================
WARNING! Incompatible PHP version $curversion
================================================================================

This CRON script must be run using PHP version $minphp or later. Your server is
currently using a much older version which would cause this script to crash. As
a result we have aborted execution of the script. Please contact your host and
ask them for the correct path to the PHP CLI binary for PHP $minphp or later, then
edit your CRON job and replace your current path to PHP with the one your host
gave you.

For your information, the current PHP version information is as follows.

PATH:    $bindir
VERSION: $curversion

Further clarifications:

1. There is absolutely no possible way that you are receiving this warning in
   error. We are using the PHP_VERSION constant to detect the PHP version you
   are currently using. This is what PHP itself reports as its own version. It
   simply cannot lie.

2. Even though your *site* may be running in a higher PHP version that the one
   reported above, your CRON scripts will most likely not be running under it.
   This has to do with the fact that your site DOES NOT run under the command
   line and there are different executable files (binaries) for the web and
   command line versions of PHP.

3. Please note that you MUST NOT ask us for support about this error. We cannot
   possibly know the correct path to the PHP CLI binary as we have not set up
   your server. Your host must know and give that information.

4. The latest published versions of PHP can be found at http://www.php.net/
   Any older version is considered insecure and must NOT be used on a live
   server. If your server uses a much older version of PHP than that please
   notify them that their servers are insecure and in need of an update.

This script will now terminate.

ENDWARNING;
	die();
}

// Load system defines
if (file_exists(__DIR__ . '/defines.php'))
{
	require_once __DIR__ . '/defines.php';
}

if (!defined('_JDEFINES'))
{
	$path = rtrim(__DIR__, DIRECTORY_SEPARATOR);
	$rpos = strrpos($path, DIRECTORY_SEPARATOR);
	$path = substr($path, 0, $rpos);
	define('JPATH_BASE', $path);
	require_once JPATH_BASE . '/includes/defines.php';
}

// Load the rest of the necessary files
if (file_exists(JPATH_LIBRARIES . '/import.legacy.php'))
{
	require_once JPATH_LIBRARIES . '/import.legacy.php';
}
else
{
	require_once JPATH_LIBRARIES . '/import.php';
}
require_once JPATH_LIBRARIES . '/cms.php';

JLoader::import('joomla.application.cli');

class CmsUpdateCli extends JApplicationCli
{
	/**
	 * The Model used throughout the update process
	 *
	 * @var   CmsupdateModelUpdates
	 */
	private $model = null;

	/**
	 * JApplicationCli didn't want to run on PHP CGI. I have my way of becoming
	 * VERY convincing. Now obey your true master, you petty class!
	 *
	 * @param JInputCli $input
	 * @param JRegistry $config
	 * @param JDispatcher $dispatcher
	 */
	public function __construct(JInputCli $input = null, JRegistry $config = null, JDispatcher $dispatcher = null)
	{
		// Close the application if we are not executed from the command line, Akeeba style (allow for PHP CGI)
		if (array_key_exists('REQUEST_METHOD', $_SERVER))
		{
			die('You are not supposed to access this script from the web. You have to run it from the command line. If you don\'t understand what this means, you must not try to use this file before reading the documentation. Thank you.');
		}

		$cgiMode = false;

		if (!defined('STDOUT') || !defined('STDIN') || !isset($_SERVER['argv']))
		{
			$cgiMode = true;
		}

		// If a input object is given use it.
		if ($input instanceof JInput)
		{
			$this->input = $input;
		}
		// Create the input based on the application logic.
		else
		{
			if (class_exists('JInput'))
			{
				if ($cgiMode)
				{
					$query = "";
					if (!empty($_GET))
					{
						foreach ($_GET as $k => $v)
						{
							$query .= " $k";
							if ($v != "")
							{
								$query .= "=$v";
							}
						}
					}
					$query	 = ltrim($query);
					$argv	 = explode(' ', $query);
					$argc	 = count($argv);

					$_SERVER['argv'] = $argv;
				}

				$this->input = new JInputCLI();
			}
		}

		// If a config object is given use it.
		if ($config instanceof JRegistry)
		{
			$this->config = $config;
		}
		// Instantiate a new configuration object.
		else
		{
			$this->config = new JRegistry;
		}

		// If a dispatcher object is given use it.
		if ($dispatcher instanceof JDispatcher)
		{
			$this->dispatcher = $dispatcher;
		}
		// Create the dispatcher based on the application logic.
		else
		{
			$this->loadDispatcher();
		}

		// Load the configuration object.
		$this->loadConfiguration($this->fetchConfigurationData());

		// Set the execution datetime and timestamp;
		$this->set('execution.datetime', gmdate('Y-m-d H:i:s'));
		$this->set('execution.timestamp', time());

		// Set the current directory.
		$this->set('cwd', getcwd());
	}

	/**
	 * The main entry point of the application
	 */
	public function execute()
	{
		// Set our own exceptions handler
		restore_exception_handler();
		//set_exception_handler(array(__CLASS__, 'renderError'));

		// Required when finalising the update
		define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/com_cmsupdate');

		// Set all errors to output the messages to the console, in order to
		// avoid infinite loops in JError ;)
		restore_error_handler();
		JError::setErrorHandling(E_ERROR, 'die');
		JError::setErrorHandling(E_WARNING, 'echo');
		JError::setErrorHandling(E_NOTICE, 'echo');

		// Required by Joomla!
		JLoader::import('joomla.environment.request');

		// Load FOF
		JLoader::import('fof.include');

		// Load the ACU autoloader
		require_once JPATH_ADMINISTRATOR . '/components/com_cmsupdate/lib/autoloader.php';
		AcuAutoloader::init();

		// Load the language files
		$jlang = JFactory::getLanguage();
		$jlang->load('com_cmsupdate', JPATH_ADMINISTRATOR);

		// Display banner
		$year			 = gmdate('Y');
		$phpversion		 = PHP_VERSION;
		$phpenvironment	 = PHP_SAPI;
		$phpos			 = PHP_OS;

		$this->out("Akeeba CMS Update CLI");
		$this->out("Copyright (C) 2013-$year Akeeba Ltd. All Rights Reserved.");
		$this->out(str_repeat('-', 79));
		$this->out("Akeeba CMS Update is Free Software, distributed under the terms of the GNU");
		$this->out("General Public License version 3 or, at your option, any later version.");
		$this->out("This program comes with ABSOLUTELY NO WARRANTY as per sections 15 & 16 of the");
		$this->out("license. See http://www.gnu.org/licenses/gpl-3.0.html for details.");
		$this->out(str_repeat('-', 79));
		$this->out("You are using PHP $phpversion ($phpenvironment)");
		$this->out("");

		// Unset the time limit restrictions if PHP Safe Mode is not used on the server
		$safe_mode = true;
		if (function_exists('ini_get'))
		{
			$safe_mode = ini_get('safe_mode');
		}
		if (!$safe_mode && function_exists('set_time_limit'))
		{
			$this->out("Unsetting time limit restrictions");
			@set_time_limit(0);
		}

		// Load the Model
		$this->model = FOFModel::getTmpInstance('Updates', 'CmsupdateModel');

		// Check for updates
		$this->out('Checking for updates...');

		$updates = $this->model->getUpdateInfo();

		// Are we already up to date?
		if (!$updates->status)
		{
			$this->out('Congratulations, you have the latest Joomla! version already installed!');

			return;
		}

		// Update is found. Notify the user.
		$updateSource = $updates->source;
		$update = $updates->$updateSource;
		$newJoomlaVersion = $update['version'];

		$this->out("Update found, Joomla! $newJoomlaVersion (source: $updateSource)");

		$this->newJoomlaVersion = $newJoomlaVersion;

		// Check the FTP settings
		$ftpOptions = $this->model->getFtpOptions();
		if ($ftpOptions['enable'] && (empty($ftpOptions['user']) || empty($ftpOptions['pass'])))
		{
			throw new Exception('You have to enter your FTP username and password in your site\'s Global Configuration', 1);
		}

		// Backup before update
		if ($this->model->hasAkeebaBackup() && $this->model->hasBackupOnUpdate())
		{
			// DO NOT REMOVE! We have to preload JTableExtension to prevent it
			// from throwing an exception after backup
			$dummy = JTable::getInstance('extension');

			$input = clone $this->input;
			$model = clone $this->model;

			$this->_backupOnUpdate();

			$this->input = clone $input;
			$this->model = clone $model;

			unset($input);
			unset($model);

			$db = JFactory::getDbo();
			if (!$db->connected())
			{
				$db->connect();
			}
		}

		// Download the update
		$this->out('Preparing for download');
		$this->model->setDownloadURLFromSection($updateSource);
		$this->model->prepareDownload();
		$this->out('Downloading');
		$this->model->stepDownload(false);

		// Extract the update file
		$this->_extract();

		// Finalise update
		$this->out('Finalising update');
		$this->model->finalize(false);
		$this->out('Running update scripts');
		$this->model->runUpdateScripts();

		// Show post-update information
		$this->out('Update complete. Max memory usage ' . $this->peakMemUsage());
		$this->out('Joomla! is now updated to version ' . $newJoomlaVersion . ' (source: ' . $updateSource . ')');
	}

	/**
	 * Extracts the update package
	 *
	 * @return   boolean  True on success
	 *
	 * @throws   Exception  When the extraction fails
	 */
	private function _extract()
	{
		$this->out('Preparing to extract update');
		// Make sure Akeeba Restore is loaded
		require_once JPATH_ADMINISTRATOR . '/components/com_cmsupdate/restore.php';

		$this->model->createRestorationINI();

		require_once JPATH_ADMINISTRATOR . '/components/com_cmsupdate/restoration.php';

		$overrides = array(
			'rename_files'	 => array('.htaccess' => 'htaccess.bak'),
			'skip_files'	 => array(),
			'reset'			 => true
		);

		// Start extraction
		$this->out('Extracting update');
		AKFactory::nuke();

		foreach ($restoration_setup as $key => $value)
		{
			AKFactory::set($key, $value);
		}

		AKFactory::set('kickstart.enabled', true);
		$engine	 = AKFactory::getUnarchiver($overrides);
		$engine->tick();
		$ret	 = $engine->getStatusArray();

		while ($ret['HasRun'] && !$ret['Error'])
		{
			$this->out('    Extractor tick');
			$timer	 = AKFactory::getTimer();
			$timer->resetTime();
			$engine->tick();
			$ret	 = $engine->getStatusArray();
		}

		if ($ret['Error'])
		{
			throw new Exception('Extraction failed');

			return false;
		}

		return true;
	}

	private function _backupOnUpdate()
	{
		$profile = $this->model->getBackupProfile();

		if ($profile <= 0)
		{
			$profile = 1;
		}

		// Load the language files
		$paths	 = array(JPATH_ADMINISTRATOR, JPATH_ROOT);
		$jlang	 = JFactory::getLanguage();
		$jlang->load('com_akeeba', $paths[0], 'en-GB', true);
		$jlang->load('com_akeeba', $paths[1], 'en-GB', true);
		$jlang->load('com_akeeba' . '.override', $paths[0], 'en-GB', true);
		$jlang->load('com_akeeba' . '.override', $paths[1], 'en-GB', true);

		// Load Akeeba Backup's version file
		require_once JPATH_ADMINISTRATOR . '/components/com_akeeba/version.php';

		$version		 = AKEEBA_VERSION;
		$date			 = AKEEBA_DATE;
		$start_backup	 = time();

		$phpversion		 = PHP_VERSION;
		$phpenvironment	 = PHP_SAPI;
		$phpos			 = PHP_OS;

		echo <<<ENDBLOCK
Backing up before update
    Akeeba Backup $version ($date)
    ---------------------------------------------------------------------------
    Akeeba Backup is Free Software, distributed under the terms of the GNU
    General Public License version 3 or, at your option, any later version.
    This program comes with ABSOLUTELY NO WARRANTY as per sections 15 & 16 of
    the license. See http://www.gnu.org/licenses/gpl-3.0.html for details.
    ---------------------------------------------------------------------------
    You are using PHP $phpversion ($phpenvironment) on $phpos

    Starting a new backup with the following parameters:
        Profile ID  $profile

ENDBLOCK;

		// Attempt to use an infinite time limit, in case you are using the PHP CGI binary instead
		// of the PHP CLI binary. This will not work with Safe Mode, though.
		$safe_mode = true;
		if (function_exists('ini_get'))
		{
			$safe_mode = ini_get('safe_mode');
		}
		if (!$safe_mode && function_exists('set_time_limit'))
		{
			echo "    Unsetting time limit restrictions.\n";
			@set_time_limit(0);
		}
		elseif (!$safe_mode)
		{
			echo "    Could not unset time limit restrictions; you may get a timeout error\n";
		}
		else
		{
			echo "    You are using PHP's Safe Mode; you may get a timeout error\n";
		}
		echo "\n";

		// Log some paths
		echo "    Site paths determined by this script:\n";
		echo "        JPATH_BASE : " . JPATH_BASE . "\n";
		echo "        JPATH_ADMINISTRATOR : " . JPATH_ADMINISTRATOR . "\n\n";

		// Load the engine
		$factoryPath = JPATH_ADMINISTRATOR . '/components/com_akeeba/akeeba/factory.php';
		define('AKEEBAROOT', JPATH_ADMINISTRATOR . '/components/com_akeeba/akeeba');

		if (!file_exists($factoryPath))
		{
			echo "ERROR!\n";
			echo "Could not load the backup engine; file does not exist. Technical information:\n";
			echo "Path to " . basename(__FILE__) . ": " . __DIR__ . "\n";
			echo "Path to factory file: $factoryPath\n";
			die("\n");
		}
		else
		{
			try
			{
				require_once $factoryPath;
			}
			catch (Exception $e)
			{
				echo "ERROR!\n";
				echo "Backup engine returned an error. Technical information:\n";
				echo "Error message:\n\n";
				echo $e->getMessage() . "\n\n";
				echo "Path to " . basename(__FILE__) . ":" . __DIR__ . "\n";
				echo "Path to factory file: $factoryPath\n";
				die("\n");
			}
		}

		// Forced CLI mode settings
		define('AKEEBA_PROFILE', $profile);
		define('AKEEBA_BACKUP_ORIGIN', 'cli');

		// Force loading CLI-mode translation class
		$dummy = new AEUtilTranslate;

		// Load the profile
		AEPlatform::getInstance()->load_configuration($profile);

		// Reset Kettenrad and its storage
		AECoreKettenrad::reset(array(
									'maxrun' => 0
							   ));
		AEUtilTempvars::reset(AKEEBA_BACKUP_ORIGIN);

		// Setup
		$kettenrad	 = AEFactory::getKettenrad();
		$options	 = array(
			'description'	 => 'Backup before updating to Joomla! ' . $this->newJoomlaVersion . ' (automatic update)',
			'comment'		 => ''
		);

		$kettenrad->setup($options);

		// Dummy array so that the loop iterates once
		$array = array(
			'HasRun' => 0,
			'Error'	 => ''
		);

		$warnings_flag = false;

		$this->out('    Starting backup');
		$this->out('');

		while (($array['HasRun'] != 1) && (empty($array['Error'])))
		{
			// Recycle the database connection to minimise problems with database timeouts
			$db = AEFactory::getDatabase();
			$db->close();
			$db->open();

			AEUtilLogger::openLog(AKEEBA_BACKUP_ORIGIN);
			AEUtilLogger::WriteLog(true, '');

			// Apply engine optimization overrides
			$config = AEFactory::getConfiguration();
			$config->set('akeeba.tuning.min_exec_time', 0);
			$config->set('akeeba.tuning.nobreak.beforelargefile', 1);
			$config->set('akeeba.tuning.nobreak.afterlargefile', 1);
			$config->set('akeeba.tuning.nobreak.proactive', 1);
			$config->set('akeeba.tuning.nobreak.finalization', 1);
			$config->set('akeeba.tuning.settimelimit', 0);
			$config->set('akeeba.tuning.nobreak.domains', 0);

			$kettenrad->tick();
			AEFactory::getTimer()->resetTime();
			$array		 = $kettenrad->getStatusArray();
			AEUtilLogger::closeLog();
			$time		 = date('Y-m-d H:i:s \G\M\TO (T)');

			$warnings		 = "no warnings issued (good)";
			$stepWarnings	 = false;
			if (!empty($array['Warnings']))
			{
				$warnings_flag	 = true;
				$warnings		 = "POTENTIAL PROBLEMS DETECTED; " . count($array['Warnings']) . " warnings issued (see below).\n";
				foreach ($array['Warnings'] as $line)
				{
					$warnings .= "\t$line\n";
				}
				$stepWarnings = true;
				$kettenrad->resetWarnings();
			}

			echo <<<ENDSTEPINFO
    Last Tick   : $time
    Domain      : {$array['Domain']}
    Step        : {$array['Step']}
    Substep     : {$array['Substep']}
    Warnings    : $warnings


ENDSTEPINFO;
		}

		// Clean up
		AEUtilTempvars::reset(AKEEBA_BACKUP_ORIGIN);

		if (!empty($array['Error']))
		{
			throw new Exception($array['Error'], 2);
		}
		else
		{
			$this->out('Backup before update completed successfully.');
			$this->out('Peak memory usage: ' . $this->peakMemUsage());
			$this->out('Proceeding with update.');
		}
	}

	/**
	 * Returns the peak memory usage in human readable format
	 *
	 * @return  string
	 */
	private function peakMemUsage()
	{
		if (function_exists('memory_get_peak_usage'))
		{
			$size	 = memory_get_peak_usage();
			$unit	 = array('b', 'Kb', 'Mb', 'Gb', 'Tb', 'Pb');
			return @round($size / pow(1024, ($i		 = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
		}
		else
		{
			return "(unknown)";
		}
	}

	/**
	 * Exception handler for the CLI application
	 *
	 * @param   Exception  $error  The exception being handled
	 */
	public static function renderError(Exception $error)
	{
		echo "\n" . str_repeat('*', 79) . "\nERROR" . str_repeat('*', 79) . "\n";
		echo "An error occurred while processing Joomla! updates:\n\n";
		echo $error->getMessage();
		echo "\n\nThe update check has been cancelled.\n";

		exit($error->getCode());
	}
}

JApplicationCli::getInstance('CmsUpdateCli')->execute();