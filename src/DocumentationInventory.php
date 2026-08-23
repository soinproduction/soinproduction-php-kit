<?php
declare(strict_types=1);

namespace SoinProduction\Kit;

final class DocumentationInventory {
	private const CATEGORIES = [ 'platform', 'acf', 'plugins' ];

	/** @return array<string, mixed> */
	public static function collect(string $themeRoot, array $moduleConfig = []): array {
		$themeRoot = rtrim(str_replace('\\', '/', $themeRoot), '/');
		if ($themeRoot === '' || !is_dir($themeRoot)) {
			throw new \RuntimeException('Theme root is not a readable directory.');
		}

		$style    = self::read($themeRoot . '/style.css');
		$composer = self::json($themeRoot . '/composer.json');
		$package  = self::json($themeRoot . '/package.json');

		return [
			'metadata' => [
				'name'      => self::header($style, 'Theme Name'),
				'version'   => self::header($style, 'Version'),
				'textDomain'=> self::header($style, 'Text Domain'),
				'wordpress' => self::header($style, 'Requires at least'),
				'php'       => (string) ($composer['require']['php'] ?? self::header($style, 'Requires PHP')),
				'node'      => (string) ($package['engines']['node'] ?? ''),
			],
			'modules'  => self::modules($themeRoot, $moduleConfig),
			'content'  => self::contentTypes($themeRoot . '/core/cpt'),
			'sections' => self::components($themeRoot . '/template_parts', 'section-'),
			'blocks'   => self::components($themeRoot . '/acf/blocks-layout', 'block-'),
			'acf'      => self::directories($themeRoot . '/acf', [ 'blocks-layout' ]),
			'frontend' => self::fileStems($themeRoot . '/js/components', 'js'),
			'templates'=> self::templates($themeRoot),
		];
	}

	/** @param array<string, mixed> $inventory */
	public static function renderMarkdown(array $inventory, string $language = 'en'): string {
		$ru       = str_starts_with(strtolower($language), 'ru');
		$metadata = is_array($inventory['metadata'] ?? null) ? $inventory['metadata'] : [];
		$lines    = [];

		$lines[] = $ru ? '# Текущая конфигурация темы' : '# Current Theme Configuration';
		$lines[] = '';
		$lines[] = $ru
			? '> Этот файл сгенерирован из фактического кода и конфигурации. Не редактируйте таблицы вручную.'
			: '> This file is generated from the actual code and configuration. Do not edit its tables manually.';
		$lines[] = '';
		$lines[] = $ru ? '## Требования и метаданные' : '## Requirements and Metadata';
		$lines[] = '';
		$lines[] = '| ' . ($ru ? 'Параметр | Значение' : 'Item | Value') . ' |';
		$lines[] = '| --- | --- |';
		$labels = $ru
			? [ 'name' => 'Тема', 'version' => 'Версия', 'textDomain' => 'Text domain', 'wordpress' => 'WordPress', 'php' => 'PHP', 'node' => 'Node.js' ]
			: [ 'name' => 'Theme', 'version' => 'Version', 'textDomain' => 'Text domain', 'wordpress' => 'WordPress', 'php' => 'PHP', 'node' => 'Node.js' ];
		foreach ($labels as $key => $label) {
			$lines[] = '| ' . $label . ' | `' . self::cell((string) ($metadata[$key] ?? '—')) . '` |';
		}

		$lines[] = '';
		$lines[] = $ru ? '## Подключённые модули PHP Kit' : '## Connected PHP Kit Modules';
		$lines[] = '';
		$lines[] = '| ' . ($ru ? 'Категория | Модуль | Наличие | Публичная конфигурация' : 'Category | Module | Availability | Public configuration') . ' |';
		$lines[] = '| --- | --- | --- | --- |';
		$modules = is_array($inventory['modules'] ?? null) ? $inventory['modules'] : [];
		if ($modules === []) {
			$lines[] = '| — | — | — | — |';
		}
		foreach ($modules as $module) {
			$available = !empty($module['available']);
			$lines[] = sprintf(
				'| `%s` | `%s` | %s | %s |',
				self::cell((string) ($module['category'] ?? '')),
				self::cell((string) ($module['name'] ?? '')),
				$available ? ($ru ? 'доступен' : 'available') : ($ru ? '**отсутствует**' : '**missing**'),
				self::cell((string) ($module['summary'] ?? '—'))
			);
		}

		self::appendComponentTable($lines, $inventory['content'] ?? [], $ru ? 'Контентные модули темы' : 'Theme Content Modules', $ru, true);
		self::appendComponentTable($lines, $inventory['sections'] ?? [], $ru ? 'Секции builder' : 'Builder Sections', $ru);
		self::appendComponentTable($lines, $inventory['blocks'] ?? [], $ru ? 'Переиспользуемые блоки' : 'Reusable Blocks', $ru);

		$lines[] = '';
		$lines[] = $ru ? '## ACF-каталоги темы' : '## Theme ACF Directories';
		$lines[] = '';
		$acf = is_array($inventory['acf'] ?? null) ? $inventory['acf'] : [];
		$lines[] = $acf === [] ? '—' : implode(', ', array_map(static fn(string $item): string => '`acf/' . $item . '/`', $acf));

		$lines[] = '';
		$lines[] = $ru ? '## Frontend-компоненты' : '## Frontend Components';
		$lines[] = '';
		$frontend = is_array($inventory['frontend'] ?? null) ? $inventory['frontend'] : [];
		$lines[] = $frontend === [] ? '—' : implode(', ', array_map(static fn(string $item): string => '`js/components/' . $item . '.js`', $frontend));

		$lines[] = '';
		$lines[] = $ru ? '## Корневые шаблоны' : '## Root Templates';
		$lines[] = '';
		$templates = is_array($inventory['templates'] ?? null) ? $inventory['templates'] : [];
		$lines[] = $templates === [] ? '—' : implode(', ', array_map(static fn(string $item): string => '`' . $item . '`', $templates));

		$lines[] = '';
		$lines[] = $ru ? '## Источники истины' : '## Sources of Truth';
		$lines[] = '';
		$lines[] = $ru
			? '- Метаданные: `style.css`, требования PHP: `composer.json`, Node.js: `package.json`.'
			: '- Metadata: `style.css`; PHP requirement: `composer.json`; Node.js requirement: `package.json`.';
		$lines[] = $ru
			? '- Модули: `config/php-kit.php`; секции, блоки и CPT: только реально существующие каталоги с обязательными файлами.'
			: '- Modules: `config/php-kit.php`; sections, blocks and CPTs: only existing directories with their required files.';
		$lines[] = $ru
			? '- `npm run build` всегда пересобирает этот файл; `npm test` проверяет отсутствие рассинхронизации.'
			: '- `npm run build` always regenerates this file; `npm test` verifies that it is not stale.';

		return implode("\n", $lines) . "\n";
	}

	/** @return array<int, array<string, mixed>> */
	private static function modules(string $themeRoot, array $config): array {
		$kitRoot = $themeRoot . '/vendor/soinproduction/php-kit';
		$modules = [];
		foreach (self::CATEGORIES as $category) {
			$selected = $config[$category] ?? [];
			if (!is_array($selected)) {
				continue;
			}
			foreach ($selected as $key => $value) {
				$name       = is_string($key) ? $key : $value;
				$moduleData = is_string($key) && is_array($value) ? $value : [];
				if (!is_string($name) || $name === '' || str_starts_with($name, '_')) {
					continue;
				}
				$path = $kitRoot . '/' . $category . '/' . $name;
				$modules[] = [
					'category'  => $category,
					'name'      => $name,
					'available' => is_dir($path) || is_file($path . '.php'),
					'summary'   => self::configSummary($moduleData),
				];
			}
		}

		usort($modules, static fn(array $a, array $b): int => strnatcasecmp($a['category'] . '/' . $a['name'], $b['category'] . '/' . $b['name']));
		return $modules;
	}

	/** @return array<int, array<string, mixed>> */
	private static function components(string $root, string $prefix): array {
		$directories = is_dir($root) ? glob($root . '/' . $prefix . '*', GLOB_ONLYDIR) : [];
		if (!is_array($directories)) {
			return [];
		}
		$items = [];
		foreach ($directories as $directory) {
			$name = basename($directory);
			if ($name === '' || str_starts_with($name, '_') || !is_file($directory . '/fields.php') || !is_file($directory . '/index.php')) {
				continue;
			}
			$items[] = [
				'name'    => $name,
				'php'     => true,
				'fields'  => true,
				'js'      => is_file($directory . '/index.js'),
				'scss'    => is_file($directory . '/style.scss'),
				'preview' => is_file($directory . '/preview.png') || is_file($directory . '/preview.webp') || is_file($directory . '/preview.jpg'),
			];
		}
		usort($items, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
		return $items;
	}

	/** @return array<int, array<string, mixed>> */
	private static function contentTypes(string $root): array {
		$directories = is_dir($root) ? glob($root . '/*', GLOB_ONLYDIR) : [];
		if (!is_array($directories)) {
			return [];
		}
		$items = [];
		foreach ($directories as $directory) {
			$name = basename($directory);
			if ($name === '' || str_starts_with($name, '_') || !is_file($directory . '/index.php')) {
				continue;
			}
			$source = implode("\n", array_map([self::class, 'read'], glob($directory . '/*.php') ?: []));
			$items[] = [
				'name'    => $name,
				'php'     => true,
				'fields'  => is_file($directory . '/fields.php'),
				'js'      => false,
				'scss'    => false,
				'preview' => false,
				'registers'=> self::registrations($source),
			];
		}
		usort($items, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
		return $items;
	}

	/** @return array<int, string> */
	private static function registrations(string $source): array {
		$variables = [];
		if (preg_match_all('/\$(post_type|taxonomy)\s*=\s*[\'\"]([^\'\"]+)[\'\"]/', $source, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$variables[$match[1]] = $match[2];
			}
		}
		$found = [];
		if (preg_match_all('/register_(post_type|taxonomy)\s*\(\s*(?:[\'\"]([^\'\"]+)[\'\"]|\$(post_type|taxonomy))/', $source, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$value = $match[2] !== '' ? $match[2] : ($variables[$match[3]] ?? '');
				if ($value !== '') {
					$found[] = ($match[1] === 'taxonomy' ? 'taxonomy:' : 'post_type:') . $value;
				}
			}
		}
		return array_values(array_unique($found));
	}

	/** @return array<int, string> */
	private static function directories(string $root, array $exclude = []): array {
		$directories = is_dir($root) ? glob($root . '/*', GLOB_ONLYDIR) : [];
		$names = [];
		foreach (is_array($directories) ? $directories : [] as $directory) {
			$name = basename($directory);
			if ($name !== '' && !str_starts_with($name, '_') && !in_array($name, $exclude, true)) {
				$names[] = $name;
			}
		}
		natcasesort($names);
		return array_values($names);
	}

	/** @return array<int, string> */
	private static function templates(string $themeRoot): array {
		$files = glob($themeRoot . '/*.php') ?: [];
		$names = array_map('basename', $files);
		natcasesort($names);
		return array_values($names);
	}

	/** @return array<int, string> */
	private static function fileStems(string $root, string $extension): array {
		$files = is_dir($root) ? glob($root . '/*.' . ltrim($extension, '.')) : [];
		$names = [];
		foreach (is_array($files) ? $files : [] as $file) {
			$name = pathinfo($file, PATHINFO_FILENAME);
			if ($name !== '' && !str_starts_with($name, '_')) {
				$names[] = $name;
			}
		}
		natcasesort($names);
		return array_values($names);
	}

	/** @param array<int, string> $lines @param mixed $rawItems */
	private static function appendComponentTable(array &$lines, $rawItems, string $title, bool $ru, bool $content = false): void {
		$items   = is_array($rawItems) ? $rawItems : [];
		$lines[] = '';
		$lines[] = '## ' . $title;
		$lines[] = '';
		if ($content) {
			$lines[] = '| ' . ($ru ? 'Каталог | Зарегистрированные объекты | Поля' : 'Directory | Registered objects | Fields') . ' |';
			$lines[] = '| --- | --- | --- |';
			foreach ($items as $item) {
				$registrations = $item['registers'] ?? [];
				$lines[] = sprintf('| `%s` | %s | %s |', self::cell((string) ($item['name'] ?? '')), self::cell($registrations === [] ? '—' : implode(', ', $registrations)), !empty($item['fields']) ? ($ru ? 'да' : 'yes') : '—');
			}
		} else {
			$lines[] = '| ' . ($ru ? 'Компонент | PHP | Поля | JS | SCSS | Preview' : 'Component | PHP | Fields | JS | SCSS | Preview') . ' |';
			$lines[] = '| --- | --- | --- | --- | --- | --- |';
			foreach ($items as $item) {
				$flag = static fn(string $key): string => !empty($item[$key]) ? ($ru ? 'да' : 'yes') : '—';
				$lines[] = sprintf('| `%s` | %s | %s | %s | %s | %s |', self::cell((string) ($item['name'] ?? '')), $flag('php'), $flag('fields'), $flag('js'), $flag('scss'), $flag('preview'));
			}
		}
		if ($items === []) {
			$lines[] = $content ? '| — | — | — |' : '| — | — | — | — | — | — |';
		}
	}

	/** @param array<string, mixed> $config */
	private static function configSummary(array $config): string {
		if ($config === []) {
			return '—';
		}
		$parts = [];
		$listValues = [];
		foreach ($config as $key => $value) {
			$key = (string) $key;
			if (ctype_digit($key) && is_string($value) && $value !== '' && !str_starts_with($value, '_')) {
				$listValues[] = $value;
				continue;
			}
			if (preg_match('/token|secret|password|binary|command|home|path/i', $key)) {
				continue;
			}
			if (is_bool($value) || is_scalar($value)) {
				$parts[] = $key . '=' . (is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
			} elseif (is_array($value)) {
				$flat = array_values(array_filter($value, static fn($item): bool => is_scalar($item) && (!is_string($item) || !str_starts_with($item, '_'))));
				$parts[] = $key . ($flat === [] ? '' : '=' . implode(', ', array_map('strval', $flat)));
			}
		}
		if ($listValues !== []) {
			array_unshift($parts, 'modules=' . implode(', ', $listValues));
		}
		return $parts === [] ? 'configured' : implode('; ', $parts);
	}

	/** @return array<string, mixed> */
	private static function json(string $file): array {
		$content = self::read($file);
		$data = $content !== '' ? json_decode($content, true) : null;
		return is_array($data) ? $data : [];
	}

	private static function header(string $source, string $field): string {
		return preg_match('/^\s*' . preg_quote($field, '/') . ':\s*(.+)$/mi', $source, $match) === 1 ? trim($match[1]) : '—';
	}

	private static function read(string $file): string {
		$content = is_readable($file) ? file_get_contents($file) : false;
		return is_string($content) ? $content : '';
	}

	private static function cell(string $value): string {
		$value = trim($value);
		return str_replace(["\r", "\n", '|'], [' ', ' ', '\\|'], $value === '' ? '—' : $value);
	}
}
