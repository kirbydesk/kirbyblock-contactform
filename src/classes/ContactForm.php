<?php

use Kirby\Cms\Page;
use Kirby\Toolkit\V;

/**
 * The contact form: its fields and sending a request – nothing is stored.
 * It sends through the project's mail server (kirby-projectwizard's
 * pwMail, Project Wizard › Settings › Sending). The contact form's own mail
 * server from before (content/.projectwizard/contactform.json) is taken
 * over once.
 */
class pwContactForm
{
	/** The fields in their start order: [show, required, width] */
	public const FIELDS = [
		'name'    => [true, true, 'half'],
		'email'   => [true, true, 'half'],
		'phone'   => [true, false, 'half'],
		'company' => [false, false, 'half'],
		'subject' => [false, false, 'full'],
		'message' => [true, true, 'full'],
	];

	/** Seconds a person needs at least between loading and sending */
	public const MIN_SECONDS = 3;

	/* -------------- The mail server: the project's --------------*/

	/** The project's mail server set up (the old settings taken over first) */
	public static function ready(): bool
	{
		if (!class_exists('pwMail')) return false;
		self::migrate();
		return pwMail::ready();
	}

	/** Where the requests go without a recipient in the block */
	public static function recipients(): array
	{
		return class_exists('pwMail') ? pwMail::recipient() : [];
	}

	/**
	 * The contact form's own mail server (up to 1.0): its settings and the
	 * encrypted password go to the project's once – where nothing is set
	 * there yet – then the old file is gone
	 */
	public static function migrate(): void
	{
		$file = pwConfig::projectDir() . '/contactform.json';
		if (!is_file($file) || !class_exists('pwMail') || !method_exists('pwMail', 'adopt')) return;
		$old = json_decode((string) file_get_contents($file), true);
		if (is_array($old)) {
			pwMail::adopt($old, isset($old['password']) ? (string) $old['password'] : null);
		}
		@unlink($file);
	}

	/* -------------- The block's fields --------------*/

	/**
	 * The fields of a block in their order: [key, show, required, width] –
	 * e-mail always shown and required (otherwise nobody can answer)
	 */
	public static function fields(array $content): array
	{
		$rows = $content['formfields'] ?? null;
		if (is_string($rows)) $rows = \Kirby\Data\Yaml::decode($rows);
		$fields = [];
		foreach (is_array($rows) ? $rows : [] as $row) {
			$key = $row['field'] ?? '';
			if (!isset(self::FIELDS[$key]) || isset($fields[$key])) continue;
			$fields[$key] = [
				'key'      => $key,
				'show'     => self::bool($row['show'] ?? self::FIELDS[$key][0]),
				'required' => self::bool($row['required'] ?? self::FIELDS[$key][1]),
				'width'    => ($row['width'] ?? self::FIELDS[$key][2]) === 'full' ? 'full' : 'half',
			];
		}
		// fields missing in the block: at their place from the start order
		foreach (self::FIELDS as $key => [$show, $required, $width]) {
			if (!isset($fields[$key])) $fields[$key] = ['key' => $key, 'show' => $fields === [] ? $show : false, 'required' => $required, 'width' => $width];
		}
		$fields['email']['show'] = true;
		$fields['email']['required'] = true;
		return array_values(array_filter($fields, fn ($f) => $f['show']));
	}

	/** The start rows of the structure field (Panel) */
	public static function defaultRows(): array
	{
		$rows = [];
		foreach (self::FIELDS as $key => [$show, $required, $width]) {
			$rows[] = ['field' => $key, 'show' => $show, 'required' => $required, 'width' => $width];
		}
		return $rows;
	}

	private static function bool($value): bool
	{
		return $value === true || $value === 'true' || $value === 1 || $value === '1';
	}

	/* -------------- Spam --------------*/

	/** A signed time stamp for the form: sending earlier than MIN_SECONDS after it is a bot */
	public static function stamp(): string
	{
		$time = (string) time();
		return $time . '.' . substr(hash_hmac('sha256', $time, self::stampSecret()), 0, 16);
	}

	private static function stampSecret(): string
	{
		return hash('sha256', kirby()->root('index') . '|' . kirby()->root('config') . '|' . __FILE__);
	}

	private static function isSpam(array $data): bool
	{
		// the hidden field only bots fill in
		if (trim((string) ($data['website'] ?? '')) !== '') return true;
		[$time, $signature] = array_pad(explode('.', (string) ($data['stamp'] ?? ''), 2), 2, '');
		if (!ctype_digit($time) || !hash_equals(substr(hash_hmac('sha256', $time, self::stampSecret()), 0, 16), $signature)) return true;
		return time() - (int) $time < self::MIN_SECONDS;
	}

	/* -------------- Sending --------------*/

	/** The block with this id on the page (also inside columns …) – its content */
	public static function findBlock(Page $page, string $id): ?array
	{
		$found = null;
		$walk = function (array $list) use (&$walk, &$found, $id) {
			foreach ($list as $block) {
				if ($found !== null || !is_array($block)) continue;
				if (($block['id'] ?? '') === $id && ($block['type'] ?? '') === 'pwcontactform') {
					$found = $block['content'] ?? [];
					return;
				}
				foreach ($block['content'] ?? [] as $value) {
					$inner = is_string($value) && str_starts_with(ltrim($value), '[{') ? json_decode($value, true) : null;
					if (is_array($inner)) $walk($inner);
				}
			}
		};
		foreach ($page->content()->toArray() as $value) {
			$list = is_string($value) && str_starts_with(ltrim($value), '[{') ? json_decode($value, true) : null;
			if (is_array($list)) $walk($list);
		}
		return $found;
	}

	/**
	 * A request: checked, then sent. Returns ['status' => sent|invalid|failed,
	 * 'errors' => [field => text], 'values' => …] for the page after the
	 * redirect. Spam gets "sent" without sending anything.
	 */
	public static function handle(Page $page, array $block, array $data): array
	{
		$fields = self::fields($block);
		$values = [];
		foreach ($fields as $field) {
			$values[$field['key']] = trim((string) ($data[$field['key']] ?? ''));
		}
		if (self::isSpam($data)) return ['status' => 'sent'];

		$errors = [];
		foreach ($fields as $field) {
			$key = $field['key'];
			if ($field['required'] && $values[$key] === '') $errors[$key] = 'required';
			elseif ($key === 'email' && $values[$key] !== '' && !V::email($values[$key])) $errors[$key] = 'email';
		}
		if (empty($data['consent'])) $errors['consent'] = 'required';
		if ($errors !== []) return ['status' => 'invalid', 'errors' => $errors, 'values' => $values];

		try {
			self::send($page, $block, $fields, $values);
		} catch (\Throwable $e) {
			kirby()->trigger('pwcontactform.failed', ['error' => $e->getMessage()]);
			return ['status' => 'failed', 'values' => $values];
		}
		return ['status' => 'sent'];
	}

	private static function send(Page $page, array $block, array $fields, array $values): void
	{
		if (!self::ready()) throw new \Exception('No mail server');
		$site = site()->title()->value();

		// the request: to the block's recipients, else the project's
		$recipients = class_exists('pwMail') ? pwMail::addresses(trim((string) ($block['recipient'] ?? ''))) : [];
		if ($recipients === []) $recipients = self::recipients();
		if ($recipients === []) throw new \Exception('No recipient');

		$lines = [];
		foreach ($fields as $field) {
			if ($values[$field['key']] === '') continue;
			$lines[] = t('kirbyblock-contactform.field.' . $field['key']) . ': ' . ($field['key'] === 'message' ? "\n" : '') . $values[$field['key']];
		}
		$lines[] = '';
		$lines[] = '— ' . $page->url();
		$subject = trim((string) ($block['mailsubject'] ?? '')) ?: tt('kirbyblock-contactform.mail.subject.default', null, ['site' => $site]);
		$subject = self::fill($subject, $values);

		pwMail::send([
			'replyTo' => $values['email'],
			'to'      => $recipients,
			'subject' => trim($subject, " :–-"),
			'body'    => implode("\n", $lines),
		]);

		// the confirmation to the visitor: the block's text and a copy
		if (self::bool($block['confirm'] ?? false)) {
			$text = self::fill(trim((string) ($block['confirmtext'] ?? '')), $values);
			pwMail::send([
				'replyTo' => $recipients[0],
				'to'      => $values['email'],
				'subject' => tt('kirbyblock-contactform.mail.confirm.subject', null, ['site' => $site]),
				'body'    => $text . "\n\n" . t('kirbyblock-contactform.mail.confirm.copy') . "\n\n" . implode("\n", array_slice($lines, 0, -2)),
			]);
		}
	}

	/** {name} and {subject} (also as {{ name }}) replaced by what was entered */
	private static function fill(string $text, array $values): string
	{
		return preg_replace_callback('/\{\{?\s*(name|subject)\s*\}?\}/', fn ($m) => $values[$m[1]] ?? '', $text);
	}
}
