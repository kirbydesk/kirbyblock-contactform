<?php

// Ensure pwConfig is available when plugins are loaded in alphabetical order
// (manual installation has no composer autoloader — load order depends on filesystem)
if (!class_exists('pwConfig')) {
	$pagewizardConfig = __DIR__ . '/../kirby-pagewizard/src/helpers/blocks/config.php';
	if (file_exists($pagewizardConfig)) {
		require_once $pagewizardConfig;
	}
}

require_once __DIR__ . '/src/classes/ContactForm.php';

pwConfig::register('pwcontactform', __DIR__ . '/src/config');

Kirby::plugin('kirbydesk/kirbyblock-contactform', [
	/* -------------- Options --------------*/
	// sends mails: the Project Wizard shows Settings › Sending
	'options' => [
		'mail' => true,
	],
	/* -------------- Extensions --------------*/
	'blueprints' => require_once 'src/extensions/blueprints.php',
	'routes' => require_once 'src/extensions/routes.php',
	'snippets' => require_once 'src/extensions/snippets.php',
	'translations' => require_once 'src/extensions/translations.php'
]);
