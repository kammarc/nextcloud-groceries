<?php

declare(strict_types=1);

namespace OCA\Groceries\Public;

/**
 * One line to add. Either pass the raw text ($line, e.g. "200 g Mehl (Type 405)") and let
 * Groceries parse it, or pass already structured values; structured values win.
 *
 * @since 0.1.0
 */
final class ItemLine {
	public function __construct(
		public readonly string $line = '',
		public readonly ?string $name = null,
		public readonly ?string $quantity = null,
		public readonly ?string $note = null,
		public readonly ?bool $enabled = null,
		public readonly ?string $category = null,
	) {
	}

	/** Builds from the JSON shape used by the REST API and the Cookbook/Bring! mapping dialog. */
	public static function fromArray(array $a): self {
		return new self(
			(string)($a['line'] ?? ''),
			isset($a['name']) ? (string)$a['name'] : null,
			isset($a['quantity']) ? (string)$a['quantity'] : null,
			isset($a['note']) ? (string)$a['note'] : null,
			isset($a['enabled']) ? (bool)$a['enabled'] : null,
			isset($a['category']) ? (string)$a['category'] : null,
		);
	}

	public function toArray(): array {
		$out = ['line' => $this->line];
		foreach (['name', 'quantity', 'note', 'enabled', 'category'] as $k) {
			if ($this->$k !== null) {
				$out[$k] = $this->$k;
			}
		}
		return $out;
	}
}
