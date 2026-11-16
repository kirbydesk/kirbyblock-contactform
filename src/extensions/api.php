<?php

/**
 * The mail server for the Project Wizard (Blocks › Contact form › Sending)
 */
return [
	'routes' => [
		[
			'pattern' => 'contactform/settings',
			'method'  => 'GET',
			'action'  => fn () => pwContactForm::settings(),
		],
		[
			'pattern' => 'contactform/settings',
			'method'  => 'POST',
			'action'  => function () {
				pwContactForm::save(kirby()->request()->body()->toArray());
				return pwContactForm::settings();
			},
		],
		// a test mail to the logged-in user: the server's answer on failure
		[
			'pattern' => 'contactform/test',
			'method'  => 'POST',
			'action'  => function () {
				$to = (string) kirby()->user()?->email();
				$error = pwContactForm::test($to);
				return $error === null ? ['sent' => $to] : ['error' => $error];
			},
		],
	],
];
