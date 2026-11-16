<?php

use Kirby\Cms\Page;
use Kirby\Toolkit\V;

/**
 * The contact form: its fields, the mail server (Project Wizard → Blocks ›
 * Contact form › Sending) and sending a request by SMTP – nothing is
 * stored. The settings travel with the content
 * (content/.projectwizard/contactform.json); the password only encrypted,
 * its key lives with the code (site/config/contactform.key).
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

	/** The mail server's settings with their start values (the password apart) */
	public const SETTINGS = [
		'recipient' => '',
		'host'      => '',
		'port'      => '587',
		'security'  => 'tls',     // tls (STARTTLS) or ssl
		'username'  => '',
		'sender'    => '',
	];

	/** Seconds a person needs at least between loading and sending */
	public const MIN_SECONDS = 3;

	/* -------------- Settings --------------*/

	public static function settingsFile(): string
	{
		return pwConfig::projectDir() . '/contactform.json';
	}

	private static function stored(): array
	{
		$file = self::settingsFile();
		$data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
		return is_array($data) ? $data : [];
	}

	/** The settings for the Panel – never the password, only whether it's there and readable */
	public static function settings(): array
	{
		$stored = self::stored();
		$settings = array_merge(self::SETTINGS, array_intersect_key($stored, self::SETTINGS));
		$hasPassword = !empty($stored['password']);
		$settings['password'] = $hasPassword ? (self::password() !== null ? 'stored' : 'unreadable') : 'none';
		$settings['ready'] = self::ready();
		return $settings;
	}

	/** Changes from the Panel: a password given is stored encrypted, an empty one keeps the stored */
	public static function save(array $changes): void
	{
		$stored = self::stored();
		foreach (self::SETTINGS as $key => $default) {
			if (array_key_exists($key, $changes)) $stored[$key] = trim((string) $changes[$key]);
		}
		// (none: only for a local test server – the Project Wizard offers STARTTLS and SSL/TLS)
		if (!in_array($stored['security'] ?? 'tls', ['tls', 'ssl', 'none'], true)) $stored['security'] = 'tls';
		if (isset($changes['password']) && $changes['password'] !== '') {
			$stored['password'] = self::encrypt((string) $changes['password']);
		}
		if (!empty($changes['removePassword'])) unset($stored['password']);
		// (the start values are not stored)
		$stored = array_filter($stored, fn ($value, $key) => !(isset(self::SETTINGS[$key]) && $value === self::SETTINGS[$key]), ARRAY_FILTER_USE_BOTH);
		$file = self::settingsFile();
		if (!is_dir(dirname($file))) mkdir(dirname($file), 0755, true);
		file_put_contents($file, json_encode($stored, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	/** Everything there to send: server, user, sender and a readable password */
	public static function ready(): bool
	{
		$s = array_merge(self::SETTINGS, self::stored());
		return $s['host'] !== '' && $s['username'] !== '' && $s['sender'] !== '' && self::password() !== null;
	}

	/* -------------- The password, encrypted --------------*/

	/** The key with the code: made on the first save in the Panel */
	private static function keyFile(): string
	{
		return kirby()->root('config') . '/contactform.key';
	}

	private static function key(bool $create = false): ?string
	{
		$file = self::keyFile();
		if (is_file($file)) {
			$key = base64_decode(trim((string) file_get_contents($file)), true);
			if ($key !== false && strlen($key) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) return $key;
		}
		if (!$create) return null;
		$key = sodium_crypto_secretbox_keygen();
		file_put_contents($file, base64_encode($key) . "\n");
		return $key;
	}

	private static function encrypt(string $plain): string
	{
		$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
		return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key(true)));
	}

	/** The password – null without one or when it can't be read (no key on this server) */
	private static function password(): ?string
	{
		$stored = self::stored()['password'] ?? '';
		$key = self::key();
		$raw = $stored !== '' ? base64_decode($stored, true) : false;
		if ($raw === false || $key === null || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return null;
		$plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $key);
		return $plain === false ? null : $plain;
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
		return hash('sha256', kirby()->root('index') . '|' . (string) (self::key() !== null ? base64_encode(self::key()) : __FILE__));
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

	/** The SMTP transport for Kirby's email() */
	private static function transport(): array
	{
		$s = array_merge(self::SETTINGS, self::stored());
		return [
			'type'     => 'smtp',
			'host'     => $s['host'],
			'port'     => (int) ($s['port'] ?: 587),
			'security' => ['ssl' => 'ssl', 'none' => false][$s['security']] ?? 'tls',
			'auth'     => $s['security'] !== 'none',
			'username' => $s['username'],
			'password' => (string) self::password(),
		];
	}

	private static function send(Page $page, array $block, array $fields, array $values): void
	{
		if (!self::ready()) throw new \Exception('No mail server');
		$s = array_merge(self::SETTINGS, self::stored());
		$site = site()->title()->value();

		// the request: to the block's recipients, else the website's
		$to = trim((string) ($block['recipient'] ?? '')) ?: $s['recipient'] ?: $s['sender'];
		$recipients = array_values(array_filter(array_map('trim', explode(',', $to)), fn ($a) => V::email($a)));
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

		kirby()->email([
			'transport' => self::transport(),
			'from'      => $s['sender'],
			'fromName'  => $site,
			'replyTo'   => $values['email'],
			'to'        => $recipients,
			'subject'   => trim($subject, " :–-"),
			'body'      => implode("\n", $lines),
		]);

		// the confirmation to the visitor: the block's text and a copy
		if (self::bool($block['confirm'] ?? false)) {
			$text = trim((string) ($block['confirmtext'] ?? ''));
			$text = self::fill($text, $values);
			kirby()->email([
				'transport' => self::transport(),
				'from'      => $s['sender'],
				'fromName'  => $site,
				'replyTo'   => $recipients[0],
				'to'        => $values['email'],
				'subject'   => tt('kirbyblock-contactform.mail.confirm.subject', null, ['site' => $site]),
				'body'      => $text . "\n\n" . t('kirbyblock-contactform.mail.confirm.copy') . "\n\n" . implode("\n", array_slice($lines, 0, -2)),
			]);
		}
	}

	/** {name} and {subject} (also as {{ name }}) replaced by what was entered */
	private static function fill(string $text, array $values): string
	{
		return preg_replace_callback('/\{\{?\s*(name|subject)\s*\}?\}/', fn ($m) => $values[$m[1]] ?? '', $text);
	}

	/** Project Wizard: a test mail to the logged-in user – the server's answer on failure */
	public static function test(string $to): ?string
	{
		if (!self::ready()) return t('kirbyblock-contactform.settings.notReady');
		$s = array_merge(self::SETTINGS, self::stored());
		try {
			kirby()->email([
				'transport' => self::transport(),
				'from'      => $s['sender'],
				'fromName'  => site()->title()->value(),
				'to'        => $to,
				'subject'   => t('kirbyblock-contactform.mail.test.subject'),
				'body'      => t('kirbyblock-contactform.mail.test.body'),
			]);
		} catch (\Throwable $e) {
			return $e->getMessage();
		}
		return null;
	}
}
