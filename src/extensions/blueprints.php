<?php

return [
	'blocks/pwcontactform' => pwBlueprint::main('pwcontactform', function ($cfg) {
		$weightOption = fn (string $value) => ['value' => $value, 'text' => ['*' => 'kirbyblock-contactform.field.' . $value]];
		return [
			'name' => 'kirbyblock-contactform.name',
			'icon' => 'contactform',
			'contentFields' => array_merge(
				pwBlueprint::stdContent($cfg, ['tagline', 'heading', 'editor']),
				[
					// the fields: shown, required, half or full width – sorted by dragging
					'formFields' => [
						'label'     => 'kirbyblock-contactform.fields',
						'type'      => 'structure',
						'help'      => 'kirbyblock-contactform.fields.help',
						'sortable'  => true,
						'duplicate' => false,
						'max'       => count(pwContactForm::FIELDS),
						'default'   => pwContactForm::defaultRows(),
						'columns'   => [
							'field'    => ['width' => '1/3'],
							'show'     => ['width' => '1/6'],
							'required' => ['width' => '1/6'],
							'width'    => ['width' => '1/3'],
						],
						'fields'    => [
							'field' => [
								'label'    => 'kirbyblock-contactform.fields.field',
								'type'     => 'select',
								'required' => true,
								'options'  => array_map($weightOption, array_keys(pwContactForm::FIELDS)),
							],
							'show' => [
								'label' => 'kirbyblock-contactform.fields.show',
								'type'  => 'toggle',
								'width' => '1/3',
							],
							'required' => [
								'label' => 'kirbyblock-contactform.fields.required',
								'type'  => 'toggle',
								'width' => '1/3',
							],
							'width' => [
								'label'   => 'kirbyblock-contactform.fields.width',
								'type'    => 'toggles',
								'default' => 'half',
								'width'   => '1/3',
								'options' => [
									['value' => 'half', 'text' => ['*' => 'kirbyblock-contactform.fields.width.half']],
									['value' => 'full', 'text' => ['*' => 'kirbyblock-contactform.fields.width.full']],
								],
							],
						],
					],
					'privacyPage' => [
						'label'    => 'kirbyblock-contactform.privacy',
						'type'     => 'pages',
						'multiple' => false,
						'help'     => 'kirbyblock-contactform.privacy.help',
						'width'    => '1/2',
					],
					'buttonText' => [
						'label'       => 'kirbyblock-contactform.button',
						'type'        => 'text',
						'placeholder' => 'kirbyblock-contactform.button.default',
						'width'       => '1/2',
					],
					'successText' => [
						'label'       => 'kirbyblock-contactform.success',
						'type'        => 'textarea',
						'buttons'     => false,
						'size'        => 'small',
						'placeholder' => 'kirbyblock-contactform.success.default',
						'help'        => 'kirbyblock-contactform.success.help',
					],
				]
			),
			'extraTabs' => [
				// sending: the recipient, the subject, the confirmation
				'mail' => [
					'label'  => 'kirbyblock-contactform.tab.mail',
					'fields' => array_merge(
						pwContactForm::ready() ? [] : [
							'mailServerMissing' => [
								'type'  => 'info',
								'theme' => 'negative',
								'text'  => 'kirbyblock-contactform.mail.notReady',
							],
						],
						[
							'recipient' => [
								'label'       => 'kirbyblock-contactform.recipient',
								'type'        => 'text',
								'placeholder' => pwContactForm::settings()['recipient'] ?: null,
								'help'        => 'kirbyblock-contactform.recipient.help',
							],
							'mailSubject' => [
								'label'       => 'kirbyblock-contactform.mail.subject',
								'type'        => 'text',
								'placeholder' => 'kirbyblock-contactform.mail.subject.placeholder',
								'help'        => 'kirbyblock-contactform.mail.subject.help',
							],
							'confirm' => [
								'label' => 'kirbyblock-contactform.confirm',
								'type'  => 'toggle',
								'text'  => ['kirbyblock-contactform.confirm.off', 'kirbyblock-contactform.confirm.on'],
							],
							'confirmText' => [
								'label'   => 'kirbyblock-contactform.confirm.text',
								'type'    => 'textarea',
								'buttons' => false,
								'size'    => 'small',
								'help'    => 'kirbyblock-contactform.confirm.text.help',
								'when'    => ['confirm' => true],
							],
						]
					),
				],
			],
		];
	}),
];
