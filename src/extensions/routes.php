<?php

/**
 * Sending a request: the form posts here, the page shows the result after
 * the redirect (also without JavaScript). The language comes with the form.
 */
return [
	[
		'pattern' => 'pagewizard/contactform',
		'method'  => 'POST',
		'action'  => function () {
			$kirby = kirby();
			$data  = $kirby->request()->body()->toArray();
			if ($kirby->multilang() && ($lang = $kirby->language((string) ($data['language'] ?? '')))) {
				$kirby->setCurrentLanguage($lang->code());
				$kirby->setCurrentTranslation($lang->code());
			}
			$page  = page((string) ($data['page'] ?? ''));
			$id    = (string) ($data['block'] ?? '');
			$block = $page ? pwContactForm::findBlock($page, $id) : null;
			if (!$page || $block === null || !csrf((string) ($data['csrf'] ?? ''))) {
				return go($page ? $page->url() : site()->url());
			}
			$kirby->session()->set('pwcontactform.' . $id, pwContactForm::handle($page, $block, $data));
			return go($page->url() . '#contactform-' . $id);
		},
	],
];
