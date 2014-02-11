<?php
/**
 * @package		cmsupdate
 * @copyright	2014 Nicholas K. Dionysopoulos / Akeeba Ltd 
 * @license		GNU GPL version 3 or later
 */

// Require the restoration environment or fail cold
defined('_AKEEBA_RESTORATION') or die();

// Fake a miniature Joomla environment
if (!defined('_JEXEC'))
{
	define('_JEXEC', 1);
}

if (!function_exists('jimport'))
{
	/** We don't use it but the post-update script is using it anyway, so LET'S FAKE IT! */
	function jimport($foo = null, $bar = null)
	{
		// Do nothing :p
	}
}

// Fake the JFile class, mapping it to our post-processing class
if (!class_exists('JFile'))
{
	abstract class JFile
	{
		public static function exists($filename)
		{
			return @file_exists($filename);
		}

		public static function delete($filename)
		{
			$postproc = AKFactory::getPostProc();
			$postproc->unlink($filename);
		}
	}
}

// Fake the JFolder class, mapping it to our post-processing class
if (!class_exists('JFolder'))
{
	abstract class JFolder
	{
		public static function exists($filename)
		{
			return @is_dir($filename);
		}

		public static function delete($filename)
		{
			recursive_remove_directory($filename);
		}
	}
}

// Fake the JText class
if (!class_exists('JText'))
{
	abstract class JText
	{
		// We don't do no translation, mister!
		public static function sprintf($foobar)
		{
			return '';
		}
	}
}

if (!function_exists('finalizeRestore'))
{
	/**
	 * Run part of the Joomla! finalisation script, namely the part that cleans up unused files/folders
	 *
	 * @param   string  $siteRoot     The root to the Joomla! site
	 * @param   string  $restorePath  The base path to restore.php
	 */
	function finalizeRestore($siteRoot, $restorePath)
	{
		if (!defined('JPATH_ROOT'))
		{
			define('JPATH_ROOT', $siteRoot);
		}

		$filePath = JPATH_ROOT . '/administrator/components/com_admin/script.php';

		if (file_exists($filePath))
		{
			require_once ($filePath);
		}

		if (class_exists('JoomlaInstallerScript'))
		{
			$o = new JoomlaInstallerScript();
			$o->deleteUnexistingFiles();
		}
	}
}