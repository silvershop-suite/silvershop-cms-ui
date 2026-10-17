<?php

namespace SilverShop\CmsUi\Extensions;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldDataColumns;

/**
 * Status-aware styling for any GridField in the Silverstripe 6 admin. It is added to every GridField (via
 * _config), but opt-in per instance: nothing changes until a method is called. Two primitives, both driven
 * by a status-value => semantic-token map (success | warning | danger | info | neutral):
 *
 *   // Render a column's values as native-blend badge pills:
 *   $grid->addStatusBadges('Status', ['Paid' => 'success', 'Unpaid' => 'warning', 'Cancelled' => 'danger']);
 *
 *   // Tint the whole row by a status value:
 *   $grid->colorRowsByStatus('Status', ['Unpaid' => 'warning', 'Cancelled' => 'danger']);
 *
 *   // Suppress the framework's native title-column status flag (avoid a double badge):
 *   $grid->hideStatusFlags();
 *
 * Badges use GridFieldDataColumns::setFieldFormatting and render
 * `<span class="badge ss-badge ss-badge--{token}">{label}</span>`; row tints use the stock
 * GridField::updateNewRowClasses hook to append `ss-grid-row--{token}` to the <tr>. The colours live in
 * client/css/cms-ui.css (Bootstrap 5.3 subtle triad → native blend + dark mode for free). The module
 * stays shop-agnostic: the status => token map is supplied by the consumer (or a project-wide YAML
 * `status_tokens` default); no shop model is referenced here.
 *
 * Developers add their own colours three ways:
 *   1. The five built-in semantic tokens: success | warning | danger | info | neutral.
 *   2. A raw #hex as a map value — `['VIP' => '#7c3aed']` — renders an inline-styled pill, no CSS needed
 *      (badges only; row tints need a token/class).
 *   3. A custom named token — map a value to e.g. 'brand', then define `.ss-badge--brand` (and
 *      `.ss-grid-row--brand` for row tints) in your own CSS, mirroring cms-ui's `--ss-badge-*` vars.
 * Colours can also be set project-wide in the YAML `status_tokens` map (value => token or value => #hex).
 */
class GridFieldStatusExtension extends Extension
{
    /**
     * Project-wide default status-value => token map (overridable in YAML). Empty by default so the module
     * ships no shop-specific assumptions; a consumer passes its own map to the methods, or sets this.
     *
     * @config
     * @var array<string,string>
     */
    private static array $status_tokens = [];

    /**
     * Per-grid row-colour config: column => (value => token). Stored on the (per-owner) extension instance
     * rather than a magic GridField property, so there is no indirect-modification trap.
     *
     * @var array<string,array<string,string>>
     */
    protected array $rowStatusConfig = [];

    /**
     * Render $column's values as badge pills coloured by $map (value => token). Any value not in $map falls
     * back to the YAML `status_tokens` map, then to the 'neutral' token.
     *
     * $valueField lets the token be resolved from a DIFFERENT field than the one displayed — e.g. show the
     * translated `StatusI18N` column but key the colour off the raw `Status` enum:
     *   $grid->addStatusBadges('StatusI18N', $map, 'Status');
     * It defaults to $column (display and token share the field).
     *
     * @param array<string,string>|null $map
     */
    public function addStatusBadges(string $column, ?array $map = null, ?string $valueField = null): GridField
    {
        /** @var GridField $grid */
        $grid = $this->getOwner();
        $columns = $grid->getConfig()->getComponentByType(GridFieldDataColumns::class);
        if (!$columns) {
            return $grid;
        }
        $map = $map ?? [];
        $valueField = $valueField ?: $column;
        $formatting = $columns->getFieldFormatting() ?: [];
        $formatting[$column] = function ($value, $item) use ($map, $valueField): string {
            $raw = GridFieldStatusExtension::rawValue($item, $valueField, $value);
            return GridFieldStatusExtension::renderBadge((string) $value, (string) $raw, $map);
        };
        $columns->setFieldFormatting($formatting);
        return $grid;
    }

    /**
     * Tint the whole row by the record's $column value, using $map (value => token). Additive — several
     * calls stack. Adds `.ss-grid-colored-rows`; updateNewRowClasses then appends `.ss-grid-row--{token}`.
     *
     * @param array<string,string>|null $map
     */
    public function colorRowsByStatus(string $column, ?array $map = null): GridField
    {
        /** @var GridField $grid */
        $grid = $this->getOwner();
        $grid->addExtraClass('ss-grid-colored-rows');
        $this->rowStatusConfig[$column] = $map ?? [];
        return $grid;
    }

    /**
     * Suppress the framework's native status-flag badge (rendered on the auto-detected title column) on
     * this grid, to avoid a double badge when a custom status column is already skinned.
     */
    public function hideStatusFlags(): GridField
    {
        /** @var GridField $grid */
        $grid = $this->getOwner();
        $columns = $grid->getConfig()->getComponentByType(GridFieldDataColumns::class);
        if ($columns) {
            $columns->setDisplayStatusFlags(false);
        }
        return $grid;
    }

    /**
     * Stock GridField row-class hook: append `ss-grid-row--{token}` for each configured column whose value
     * resolves to a token. Additive — never clobbers the base `ss-gridfield-item …` classes.
     *
     * @param array<int,string> $classes
     * @param int               $total
     * @param int               $index
     * @param mixed             $record
     */
    public function updateNewRowClasses(&$classes, $total, $index, $record): void
    {
        foreach ($this->rowStatusConfig as $column => $map) {
            $raw = self::rawValue($record, $column, null);
            if ($raw === null || $raw === '') {
                continue;
            }
            $token = self::resolveToken((string) $raw, $map);
            if ($token !== null) {
                $classes[] = 'ss-grid-row--' . $token;
            }
        }
    }

    /**
     * The raw underlying value of $column on a record (for token lookup): a method accessor first (so a
     * translated/computed display label still maps on its canonical key), then the DB field, then a
     * magic property.
     *
     * @param mixed  $item
     * @param mixed  $fallback
     * @return mixed
     */
    public static function rawValue($item, string $column, $fallback)
    {
        if (!$item) {
            return $fallback;
        }
        if ($item->hasMethod($column)) {
            return $item->$column();
        }
        if ($item->hasMethod('hasField') && $item->hasField($column)) {
            return $item->getField($column);
        }
        return $item->$column ?? $fallback;
    }

    /**
     * Resolve a status value to a token via $map, then the YAML `status_tokens` default. Null if neither
     * maps it (the caller decides the fallback — badges use 'neutral', row tints skip the row).
     *
     * @param array<string,string> $map
     */
    public static function resolveToken(string $value, array $map): ?string
    {
        if (isset($map[$value])) {
            return self::sanitiseToken($map[$value]);
        }
        $defaults = (array) Config::inst()->get(self::class, 'status_tokens');
        if (isset($defaults[$value])) {
            return self::sanitiseToken((string) $defaults[$value]);
        }
        return null;
    }

    /**
     * Build the badge markup. A mapped value is either a semantic token (-> a `.ss-badge--{token}` class)
     * or a raw `#hex` colour (the developer escape hatch -> an inline-styled pill, no CSS needed). The token
     * comes from our own map (never user input) and the label is XML-escaped, so the (un-escaped)
     * setFieldFormatting output is safe.
     *
     * @param array<string,string> $map
     */
    public static function renderBadge(string $label, string $rawValue, array $map): string
    {
        if ($label === '') {
            return '';
        }
        // The mapped value for this status: a semantic token, OR a raw #hex (developer custom colour).
        $mapped = $map[$rawValue] ?? $map[$label] ?? null;
        if ($mapped === null) {
            $defaults = (array) Config::inst()->get(self::class, 'status_tokens');
            $mapped = $defaults[$rawValue] ?? $defaults[$label] ?? null;
        }

        // Raw-hex escape hatch: a #rgb / #rrggbb value → an inline-styled pill (text + border the colour,
        // a light shade behind via color-mix), so a developer can use any colour without adding CSS.
        if (is_string($mapped) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $mapped)) {
            $style = sprintf(
                'color:%1$s;border-color:%1$s;background:color-mix(in srgb, %1$s 12%%, transparent)',
                $mapped
            );
            return sprintf(
                '<span class="badge ss-badge" style="%s">%s</span>',
                Convert::raw2att($style),
                Convert::raw2xml($label)
            );
        }

        $token = $mapped !== null ? self::sanitiseToken((string) $mapped) : 'neutral';
        return sprintf(
            '<span class="badge ss-badge ss-badge--%s">%s</span>',
            Convert::raw2att($token),
            Convert::raw2xml($label)
        );
    }

    /**
     * Whitelist token chars (lowercase, digits, dash) so a token can never break out of the class
     * attribute; an empty result falls back to 'neutral'.
     */
    protected static function sanitiseToken(string $token): string
    {
        $clean = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($token))) ?? '';
        return $clean !== '' ? $clean : 'neutral';
    }
}
