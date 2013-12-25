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

// Uncomment the following line to enable debug mode
// define('CMSUPDATEDEBUG',1);

// PHP version check
if(defined('PHP_VERSION')) {
	$version = PHP_VERSION;
} elseif(function_exists('phpversion')) {
	$version = phpversion();
} else {
	$version = '5.0.0'; // all bets are off!
}
if(!version_compare($version, '5.3.1', 'ge')) return;

JLoader::import('joomla.application.plugin');

class plgSystemCmsupdateemail extends JPlugin
{
	/**
	 * Runs after the CMS has finished rendering
	 */
	public function onAfterRender()
	{
		// Is the One Click Action plugin enabled?
		$app = JFactory::getApplication();
		$jResponse = $app->triggerEvent('onOneClickActionEnabled');

		if (empty($jResponse))
		{
			return;
		}

		$status = false;

		foreach ($jResponse as $response)
		{
			$status = $status || $response;
		}

		if (!$status)
		{
			return;
		}

		// Is the CMS Update extension installed and activated?
		$filePath = JPATH_ADMINISTRATOR . '/components/com_cmsupdate/cmsupdate.php';

		if (!file_exists($filePath))
		{
			return;
		}

		// Give it a 50% chance to run. This is necessary to avoid race conditions on busy sites.
		if (!defined('CMSUPDATEDEBUG'))
		{
			if (!mt_rand(0, 1))
			{
				return;
			}
		}

		// Load FOF
		include_once JPATH_LIBRARIES . '/fof/include.php';

		if(!defined('FOF_INCLUDED') || !class_exists('FOFForm', true))
		{
			return;
		}

		// Load the ACU library's autoloader
		if (!class_exists('AcuAutoloader', false))
		{
			require_once JPATH_ADMINISTRATOR . '/components/com_cmsupdate/lib/autoloader.php';
			AcuAutoloader::init();
		}

		// Get the updates
		$model = FOFModel::getTmpInstance('Updates', 'CmsupdateModel');
		$updates = $model->getUpdateInfo();

		if (!$updates->status)
		{
			// No updates were found
			return;
		}

		$source = $updates->source;

		if (empty($source) || ($source == 'none'))
		{
			// Really, no update was found!
			return;
		}

		$update = $updates->$source;

		// Check the version. It must be different than the current version.
		if (version_compare($update['version'], JVERSION, 'eq'))
		{
			// I mean, come on, really, there are no updates!!!
			return;
		}

		// Extra sanity check: the component must be enabled.
		// DO NOT MOVE UP THE STACK! getUpdateInfo() updates the component parameters. If you call
		// JComponentHelper::getComponent before the parameters are updated, the subsequent
		// JComponentHelper::getParams will not know the parameters have been updated and you will
		// cause unnecessary update checks against the updates server.
		jimport('joomla.application.component.helper');
		$component = JComponentHelper::getComponent('com_cmsupdate', true);

		if (!$component->enabled)
		{
			return;
		}

		// Should I send an email?
		$params = JComponentHelper::getParams('com_cmsupdate');
		$lastEmail = $params->get('lastemail', 0);
		$emailFrequency = $params->get('emailfrequency', 168);
		$now = time();

		if (!defined('CMSUPDATEDEBUG') && (abs($now - $lastEmail) < ($emailFrequency * 3600)))
		{
			return;
		}

		// Update the last email timestamp
		$params->set('lastemail', $now);

		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
			->update($db->qn('#__extensions'))
			->set($db->qn('params') . ' = ' . $db->q($params->toString('JSON')))
			->where($db->qn('extension_id') . ' = ' . $db->q($component->id));
		$db->setQuery($query);
		$db->execute();

		// If we're here, we have updates. Let's create an OTP.
		$uri = JURI::base();
		$uri = rtrim($uri,'/');
		$uri .= (substr($uri,-13) != 'administrator') ? '/administrator/' : '/';
		$link = 'index.php?option=com_cmsupdate';

		// Get the super users to send the email to
		$superAdmins = array();
		$superAdminEmail = $params->get('email', '');

		if (empty($superAdminEmail))
		{
			$superAdminEmail = null;
		}

		$superAdmins = $this->_getSuperAdministrators($superAdminEmail);

		if (empty($superAdmins))
		{
			return;
		}

		// Send the email
		$this->loadLanguage();

		$email_subject = JText::_('PLG_CMSUPDATEEMAIL_EMAIL_HEADER');
		$email_body = '<p>' . JText::_('PLG_CMSUPDATEEMAIL_EMAIL_BODY_TITLE') . '</p>' .
			'<p>' . JText::_('PLG_CMSUPDATEEMAIL_EMAIL_BODY_WHOSENTIT') . '</p>' .
			'<p>' . JText::_('PLG_CMSUPDATEEMAIL_EMAIL_BODY_VERSIONINFO') . '</p>' .
			'<p>' . JText::_('PLG_CMSUPDATEEMAIL_EMAIL_BODY_INSTRUCTIONS') . '</p>' .
			'<p>' . JText::_('PLG_CMSUPDATEEMAIL_EMAIL_BODY_WHATISTHISLINK') . '</p>';

		$newVersion = $update['version'];

		$jVersion = new JVersion;
		$currentVersion = $jVersion->getShortVersion();

		$jconfig = JFactory::getConfig();
		$sitename = $jconfig->get('sitename');

		$substitutions = array(
			'[NEWVERSION]'		=> $newVersion,
			'[CURVERSION]'		=> $currentVersion,
			'[SITENAME]'		=> $sitename,
			'[PLUGINNAME]'		=> JText::_('PLG_CMSUPDATEEMAIL'),
		);

		// If Admin Tools Professional is installed, fetch the administrator secret key as well
		$adminpw = '';
		$modelFile = JPATH_ROOT.'/administrator/components/com_admintools/models/storage.php';

		if (@file_exists($modelFile))
		{
			include_once $modelFile;

			if (class_exists('AdmintoolsModelStorage'))
			{
				$model = JModelLegacy::getInstance('Storage','AdmintoolsModel');
				$adminpw = $model->getValue('adminpw','');
			}
		}

		foreach($superAdmins as $sa)
		{
			$otp = plgSystemOneclickaction::addAction($sa->id, $link);

			if (is_null($otp))
			{
				// If the OTP is null, a database error occurred
				return;
			}
			elseif (empty($otp))
			{
				// If the OTP is empty, an OTP for the same action was already
				// created and it hasn't expired.
				continue;
			}

			$emaillink = $uri . 'index.php?oneclickaction=' . $otp;

			if (!empty($adminpw))
			{
				$emaillink .= '&' . urlencode($adminpw);
			}

			$substitutions['[LINK]'] = $emaillink;

			foreach ($substitutions as $k => $v)
			{
				$email_subject = str_replace($k, $v, $email_subject);
				$email_body = str_replace($k, $v, $email_body);
			}

			$mailer = JFactory::getMailer();
			$mailfrom = $jconfig->get('mailfrom');
			$fromname = $jconfig->get('fromname');
			$mailer->isHtml(true);
			$mailer->setSender(array( $mailfrom, $fromname ));
			$mailer->addRecipient($sa->email);
			$mailer->setSubject($email_subject);
			$mailer->setBody($email_body);
			$mailer->Send();
		}
	}

	/**
	 * Returns the Super Users email information. If you provide a comma separated $email list
	 * we will check that these emails do belong to Super Users and that they have not blocked
	 * system emails.
	 *
	 * @param   null|string  $email  A list of Super Users to email
	 *
	 * @return  array  The list of Super User emails
	 */
	private function _getSuperAdministrators($email = null)
	{
		// Get a reference to the database object
		$db = JFactory::getDBO();

		// Convert the email list to an array
		if (!empty($email))
		{
			$temp = explode(',', $email);
			$emails = array();

			foreach ($temp as $entry)
			{
				$entry = trim($entry);
				$emails[] = $db->q($entry);
			}

			$emails = array_unique($emails);
		}
		else
		{
			$emails = array();
		}

		// Get a list of groups which have Super User privileges
		$ret = array();

		try
		{
			$query = $db->getQuery(true)
				->select($db->qn('rules'))
				->from($db->qn('#__assets'))
				->where($db->qn('parent_id') . ' = ' . $db->q(0));
			$db->setQuery($query, 0, 1);
			$rulesJSON	 = $db->loadResult();
			$rules		 = json_decode($rulesJSON, true);

			$rawGroups = $rules['core.admin'];
			$groups = array();

			if (empty($rawGroups))
			{
				return $ret;
			}

			foreach ($rawGroups as $g => $enabled)
			{
				if ($enabled)
				{
					$groups[] = $db->q($g);
				}
			}

			if (empty($groups))
			{
				return $ret;
			}
		}
		catch (Exception $exc)
		{
			return $ret;
		}

		// Get the user IDs of users belonging to the SA groups
		try
		{
			$query = $db->getQuery(true)
				->select($db->qn('user_id'))
				->from($db->qn('#__user_usergroup_map'))
				->where($db->qn('group_id') . ' IN(' . implode(',', $groups) . ')' );
			$db->setQuery($query);
			$rawUserIDs = $db->loadColumn(0);

			if (empty($rawUserIDs))
			{
				return $ret;
			}

			$userIDs = array();

			foreach ($rawUserIDs as $id)
			{
				$userIDs[] = $db->q($id);
			}
		}
		catch (Exception $exc)
		{
			return $ret;
		}

		// Get the user information for the Super Administrator users
		try
		{
			$query = $db->getQuery(true)
				->select(array(
							  $db->qn('id'),
							  $db->qn('username'),
							  $db->qn('email'),
						 ))->from($db->qn('#__users'))
				->where($db->qn('id') . ' IN(' . implode(',', $userIDs) . ')')
				->where($db->qn('sendEmail') . ' = ' . $db->q('1'));

			if (!empty($emails))
			{
				$query->where($db->qn('email') . 'IN(' . implode(',', $emails) . ')');
			}

			$db->setQuery($query);
			$ret = $db->loadObjectList();
		}
		catch (Exception $exc)
		{
			return $ret;
		}

		return $ret;
	}
}