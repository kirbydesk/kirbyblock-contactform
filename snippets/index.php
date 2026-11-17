<?php

// Config
$config   = pwConfig::load('pwcontactform');
$settings = $config['content'];

// Custom Background
pwSnippet::customCss($block);

// The result of a request (after the redirect): sent, invalid or failed
$session = kirby()->session();
$result  = $session->pull('pwcontactform.' . $block->id(), null);
$status  = is_array($result) ? ($result['status'] ?? '') : '';
$errors  = is_array($result) ? ($result['errors'] ?? []) : [];
$values  = is_array($result) ? ($result['values'] ?? []) : [];

// Section + Grid open
echo pwSnippet::sectionOpen('contactform', $block, $settings);
echo pwSnippet::gridOpen($block);

// Tagline
if (!empty($settings['tagline'])):
	snippet('tagline', ['content' => $block]);
endif;

// Heading
if (!empty($settings['heading'])):
	snippet('heading', ['content' => $block]);
endif;

// Editor
if (!empty($settings['editor'])):
	snippet('editor', ['content' => $block]);
endif;

$id = 'contactform-' . $block->id();

echo '<div data-block="items" id="' . $id . '">' . "\n";

if ($status === 'sent'):
	// instead of the form: the block's thanks
	$text = $block->successText()->or(t('kirbyblock-contactform.success.default'))->value();
	echo '<div data-contactform-message="sent" role="status">' . kirbytext(esc($text)) . '</div>' . "\n";

else:
	if ($status === 'failed'):
		$recipient = pwContactForm::recipients()[0] ?? '';
		echo '<div data-contactform-message="failed" role="alert">' . kirbytext(tt('kirbyblock-contactform.failed', null, ['email' => esc($recipient)])) . '</div>' . "\n";
	endif;

	$fields  = pwContactForm::fields($block->content()->toArray());
	$privacy = $block->privacyPage()->toPage();
	$error   = fn (string $key) => isset($errors[$key])
		? '<span data-contactform-error id="' . $id . '-' . $key . '-error">' . t('kirbyblock-contactform.error.' . ($key === 'consent' ? 'consent' : ($errors[$key] === 'email' ? 'email' : 'required'))) . '</span>'
		: '';
	$types = ['email' => 'email', 'phone' => 'tel'];
	$autocomplete = ['name' => 'name', 'email' => 'email', 'phone' => 'tel', 'company' => 'organization'];
	?>
<form data-contactform method="post" action="<?= url('pagewizard/contactform') ?>" novalidate>
	<input type="hidden" name="csrf" value="<?= csrf() ?>">
	<input type="hidden" name="page" value="<?= esc($page->id(), 'attr') ?>">
	<input type="hidden" name="block" value="<?= esc($block->id(), 'attr') ?>">
	<input type="hidden" name="language" value="<?= esc(kirby()->language()?->code() ?? '', 'attr') ?>">
	<input type="hidden" name="stamp" value="<?= pwContactForm::stamp() ?>">
	<?php /* only bots fill this in */ ?>
	<div data-contactform-trap aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
	<?php foreach ($fields as $field):
		$key = $field['key'];
		$name = $id . '-' . $key;
		$label = t('kirbyblock-contactform.field.' . $key);
		$attrs = ' id="' . $name . '" name="' . $key . '"' . ($field['required'] ? ' required aria-required="true"' : '') . (isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="' . $name . '-error"' : '');
		?>
	<div data-contactform-field data-width="<?= $field['width'] ?>"<?= isset($errors[$key]) ? ' data-invalid' : '' ?>>
		<label for="<?= $name ?>"><?= esc($label) ?><?= $field['required'] ? ' <span aria-hidden="true">*</span>' : '' ?></label>
		<?php if ($key === 'message'): ?>
		<textarea<?= $attrs ?> rows="6"><?= esc($values[$key] ?? '') ?></textarea>
		<?php else: ?>
		<input type="<?= $types[$key] ?? 'text' ?>"<?= $attrs ?> value="<?= esc($values[$key] ?? '', 'attr') ?>"<?= isset($autocomplete[$key]) ? ' autocomplete="' . $autocomplete[$key] . '"' : '' ?>>
		<?php endif ?>
		<?= $error($key) ?>
	</div>
	<?php endforeach ?>
	<div data-contactform-consent<?= isset($errors['consent']) ? ' data-invalid' : '' ?>>
		<input type="checkbox" id="<?= $id ?>-consent" name="consent" value="1" required<?= isset($errors['consent']) ? ' aria-invalid="true" aria-describedby="' . $id . '-consent-error"' : '' ?>>
		<label for="<?= $id ?>-consent"><?= $privacy
			? tt('kirbyblock-contactform.consent.link', null, ['link' => '<a href="' . $privacy->url() . '">' . esc(t('kirbyblock-contactform.consent.privacy')) . '</a>'])
			: t('kirbyblock-contactform.consent') ?> <span aria-hidden="true">*</span></label>
		<?= $error('consent') ?>
	</div>
	<?php /* the send button: as the website's buttons */ ?>
	<div data-contactform-submit data-field="buttons">
		<div data-field="button"><button type="submit"><span class="link-text"><?= esc($block->buttonText()->or(t('kirbyblock-contactform.button.default'))->value()) ?></span></button></div>
	</div>
</form>
<?php
endif;

echo '</div>' . "\n"; // End Items

// Close
echo pwSnippet::gridClose();
echo pwSnippet::sectionClose();
