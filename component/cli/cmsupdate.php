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

// Required by the CMS
define('DS', DIRECTORY_SEPARATOR);

// Required by restore.php to let us handle extraction manually
define('KICKSTART', 1);

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
		set_exception_handler(array(__CLASS__, 'renderError'));

		// Set all errors to output the messages to the console, in order to
		// avoid infinite loops in JError ;)
		restore_error_handler();
		JError::setErrorHandling(E_ERROR, 'die');
		JError::setErrorHandling(E_WARNING, 'echo');
		JError::setErrorHandling(E_NOTICE, 'echo');

		// Required by Joomla!
		JLoader::import('joomla.environment.request');

		// Set the root path to CMS Update
		define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/com_cmsupdate');

		// Load FOF
		JLoader::import('fof.include');

		// Load the ACU autoloader
		require_once JPATH_COMPONENT_ADMINISTRATOR . '/lib/autoloader.php';
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

		// Check the FTP settings
		$ftpOptions = $this->model->getFtpOptions();
		if ($ftpOptions['enable'] && (empty($ftpOptions['user']) || empty($ftpOptions['pass'])))
		{
			throw new Exception('You have to enter your FTP username and password in your site\'s Global Configuration', 1);
		}

		// Download the update
		$this->out('Preparing for download');
		$this->model->setDownloadURLFromSection($updateSource);
		$this->model->prepareDownload();
		$this->out('Downloading');
		$this->model->stepDownload(false);

		// @todo Backup before update

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

		require_once JPATH_COMPONENT_ADMINISTRATOR . '/restoration.php';

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