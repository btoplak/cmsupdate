<?php
$hardlink_files = array(
	# Akeeba Restore from the Akeeba Kickstart repository
	'../kickstart/source/output/restore.php'    => 'component/backend/restore.php',
);

$symlink_files = array(
	# OTP Plugin
	'../liveupdate/plugins/system/oneclickaction/LICENSE.txt'
												=> 'plugins/system/oneclickaction/LICENSE.txt',
	'../liveupdate/plugins/system/oneclickaction/oneclickaction.php'
												=> 'plugins/system/oneclickaction/oneclickaction.php',
	'../liveupdate/plugins/system/oneclickaction/oneclickaction.xml'
												=> 'plugins/system/oneclickaction/oneclickaction.xml',
);

$symlink_folders = array(
	# Build files
	'../buildfiles/bin'							=> 'build/bin',
	'../buildfiles/buildlang'					=> 'build/buildlang',
	'../buildfiles/phingext'					=> 'build/phingext',
	'../buildfiles/tools'						=> 'build/tools',

	# Component translation
	'translations/component/backend/en-GB'		=> 'component/language/backend/en-GB',
	'translations/component/frontend/en-GB'		=> 'component/language/frontend/en-GB',

	# OTP Plugin
	'../liveupdate/plugins/system/oneclickaction/sql'
												=> 'plugins/system/oneclickaction/sql',
	'../liveupdate/plugins/system/oneclickaction/language'
												=> 'translations/plugins/system/oneclickaction',
	'translations/plugins/system/oneclickaction/en-GB'
												=> 'plugins/system/oneclickaction/language/en-GB',
	# FOF
	'../fof/fof'								=> 'component/fof',

	# Akeeba Strapper
	'../fof/strapper'							=> 'component/strapper',

    // Usage library
    '../usagestats/lib'                         => 'component/backend/lib/stats'
);
