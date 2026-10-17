<?php

namespace SilverShop\CmsUi\Forms;

use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\LiteralField;

/**
 * A CompositeField that lays its children out on the Silverstripe 6 admin's Bootstrap 5 grid.
 *
 * In the admin edit form a CompositeField renders as an outer `.CompositeField` holder wrapping its
 * children one level deep inside a single `.form__field-holder`. So the grid is applied in CSS to THAT
 * inner wrapper (turned into a CSS grid), and each child field holder is given a Bootstrap `.col-*`
 * class that the CSS maps to a grid-column span. No template overrides, no JS — just class hooks
 * (addExtraClass) + `client/css/cms-ui.css`.
 *
 *   GridLayoutField::create('Details', [
 *       TextField::create('FirstName', 'First name'),
 *       TextField::create('LastName', 'Last name'),
 *   ])->setColumns(['FirstName' => 6, 'LastName' => 6]);
 *
 *   // or add fields with their span inline (int = span at the default breakpoint,
 *   // array = per-breakpoint, e.g. ['md' => 8, 'lg' => 4]):
 *   GridLayoutField::create('Row')
 *       ->addColumn(TextField::create('Street'), ['md' => 8])
 *       ->addColumn(TextField::create('Nr'), ['md' => 4]);
 *
 *   // Full page width (breaks the admin's ~58% readable field cap):
 *   GridLayoutField::create('Wide', [...])->enableFullWidth();
 *
 *   // Exact (non-12-col) ratios via an fr escape hatch (bypasses the 12-col math). Responsive: stacks to
 *   // one column below the breakpoint (default 'lg' = 992px viewport), so tablet + mobile stack and the
 *   // ratio is a desktop layout. Pass a 2nd arg to change it ('xs' = never collapse, 'md' = collapse <768):
 *   GridLayoutField::create('Split', [$main, $aside])->setGridTemplate('1.7fr 1fr');       // 2-col >= lg
 *   GridLayoutField::create('Split', [$main, $aside])->setGridTemplate('1.7fr 1fr', 'md');  // 2-col >= md
 *
 * (The escape hatch is setGridTemplate(), not setTemplate() — FormField::setTemplate() already exists
 * and sets a field's render template, so the grid-template setter is named to avoid that collision.)
 */
class GridLayoutField extends CompositeField
{
    /**
     * Gutter class applied to the row (spacing between columns). Defaults to the module's own
     * `ss-gutter-md` preset (~1.25em) rather than Bootstrap's `g-*` scale, which silverstripe/admin
     * inflates to ~3rem. Presets: ss-gutter-0 | ss-gutter-sm | ss-gutter-md | ss-gutter-lg. A
     * Bootstrap `g-*` class still works too (see setGutter()).
     */
    protected string $gutter = 'ss-gutter-md';

    /**
     * Breakpoint used when a column span is given as a bare int (e.g. 6 -> col-md-6).
     */
    protected string $defaultBreakpoint = 'md';

    /**
     * The arbitrary CSS grid-template-columns value set via setTemplate(), or null for the 12-col model.
     */
    protected ?string $gridTemplate = null;

    /**
     * The per-instance class that scopes the fr-template rule to this grid only (classes do not
     * inherit, so nested grids keep the default 12-col track model).
     */
    protected string $gridTemplateClass = '';

    /**
     * The name of the hidden LiteralField that carries the fr-template's <style> rule.
     */
    protected string $gridTemplateStyleName = '';

    /**
     * Breakpoint (VIEWPORT min-width) at/above which the fr-template applies; below it the grid stacks to
     * a single column. Default 'lg' (992px) → 2-col on desktop, stacked on tablet + mobile. 'xs'/'' =
     * always-on (never collapses).
     */
    protected string $gridTemplateBreakpoint = 'lg';

    /**
     * @param string|array|FieldList|FormField|null $nameOrChildren A name, or the children.
     * @param array|FieldList|FormField|null        $children        The children (when a name is given).
     */
    public function __construct($nameOrChildren = null, $children = null)
    {
        if (is_string($nameOrChildren)) {
            $name = $nameOrChildren;
            $kids = $children;
        } else {
            $name = null;
            $kids = $nameOrChildren;
        }

        if (!$kids instanceof FieldList) {
            $kids = FieldList::create($kids === null ? [] : (is_array($kids) ? $kids : [$kids]));
        }

        parent::__construct($kids);

        if ($name !== null) {
            $this->setName($name);
        }

        $this->applyRowClasses();
    }

    protected function applyRowClasses(): void
    {
        // `ss-grid` is the module hook; the gutter class defines the grid gap var. The Bootstrap row
        // is applied in CSS to the schema's inner field-holder wrapper (see cms-ui.css) — the CMS form
        // schema wraps a composite's children one level deep, so putting the grid on this outer holder
        // would not reach the columns.
        $this->addExtraClass(trim('ss-grid ' . $this->gutter));
    }

    /**
     * Assign column spans to existing children by field name.
     *
     * @param array<string,int|array<string,int>> $map FieldName => span (int) or [breakpoint => span].
     */
    public function setColumns(array $map): static
    {
        foreach ($map as $fieldName => $span) {
            $child = $this->fieldByName($fieldName);
            if ($child instanceof FormField) {
                $this->applyColumnClasses($child, $span);
            }
        }
        return $this;
    }

    /**
     * Push a field into the grid with its column span.
     *
     * @param int|array<string,int> $span
     */
    public function addColumn(FormField $field, int|array $span): static
    {
        $this->applyColumnClasses($field, $span);
        $this->push($field);
        return $this;
    }

    /**
     * Set the gutter class. Accepts a module preset (ss-gutter-0|sm|md|lg) or a Bootstrap g-* class.
     */
    public function setGutter(string $gutter): static
    {
        // Swap the gutter class out on the holder.
        $this->removeExtraClass($this->gutter);
        $this->gutter = $gutter;
        $this->addExtraClass($gutter);
        return $this;
    }

    /**
     * The breakpoint used for bare-int spans (default 'md'). Use 'xs' for always-on columns.
     */
    public function setBreakpoint(string $breakpoint): static
    {
        $this->defaultBreakpoint = $breakpoint;
        return $this;
    }

    /**
     * Let the grid span the full form width. By default a grid keeps the admin's readable holder
     * width (like a normal field); enableFullWidth() breaks it out to the whole form row — the
     * full-page-width mechanism, useful for wide, column-heavy layouts.
     */
    public function enableFullWidth(bool $enabled = true): static
    {
        if ($enabled) {
            $this->addExtraClass('ss-grid-full');
        } else {
            $this->removeExtraClass('ss-grid-full');
        }
        return $this;
    }

    /**
     * Escape hatch: lay the columns out with an arbitrary CSS `grid-template-columns` value (e.g.
     * '1.7fr 1fr', 'repeat(3, minmax(0, 1fr))'), bypassing the 12-col math — so a consumer can hit a
     * ratio the integer grid can't (Bootstrap 8/4 ≈ 2:1 or 7/5 don't equal 1.7:1).
     *
     * The arbitrary value is carried as a scoped <style> rule injected through a hidden LiteralField
     * child (marked .ss-grid-style so the CSS keeps it out of the grid flow). This keeps the value with
     * the field's own markup and targets its inner `.form__field-holder` wrapper directly, so it is
     * self-contained and robust — it needs no per-instance inline attribute on the holder, and a
     * per-instance class scopes the rule to this grid alone (classes do not inherit, so a nested
     * GridLayoutField keeps the 12-col model). Pass null (or an empty string) to clear it.
     *
     * Named setGridTemplate() (not setTemplate()) because FormField::setTemplate() already exists and
     * sets the field's render template — a different concern we must not shadow.
     */
    public function setGridTemplate(?string $template, string $breakpoint = 'lg'): static
    {
        // Clear any previously applied template (its class + injected <style>).
        if ($this->gridTemplateClass !== '') {
            $this->removeExtraClass($this->gridTemplateClass);
        }
        if ($this->gridTemplateStyleName !== '') {
            $this->getChildren()->removeByName($this->gridTemplateStyleName);
        }
        $this->gridTemplateClass = '';
        $this->gridTemplateStyleName = '';
        $this->gridTemplate = null;

        $template = $template === null ? '' : trim($template);
        if ($template === '') {
            return $this;
        }

        $safe = $this->sanitiseTemplate($template);
        if ($safe === '') {
            return $this;
        }

        $this->gridTemplate = $template;
        $this->gridTemplateBreakpoint = strtolower(trim($breakpoint)) ?: 'lg';
        $hash = substr(md5($safe), 0, 10);
        $this->gridTemplateClass = 'ss-grid-tpl-' . $hash;
        $this->gridTemplateStyleName = 'SSGridTpl_' . $hash;

        // The per-instance class (0,4,0) out-specifies the base 12-col rule (0,3,0), both !important.
        $this->addExtraClass($this->gridTemplateClass);

        // Responsive, mobile-first: the grid stacks to ONE column by default, and the explicit template
        // applies only at/above the breakpoint (default lg = 992px VIEWPORT) — so tablet + mobile stack
        // and the exact ratio is a desktop layout. 'xs'/'' = always-on (no query, never collapses).
        // Viewport @media, not @container: the full-width breakout widens the inner holder while this
        // composite keeps the admin's capped width, so an @container would measure the wrong width (see
        // the note in cms-ui.css). display:grid is emitted here so the rule is self-contained.
        $selector = sprintf('.CompositeField.ss-grid.%s > .form__field-holder', $this->gridTemplateClass);
        $minWidth = $this->breakpointMinWidth($this->gridTemplateBreakpoint);
        if ($minWidth > 0) {
            $css = sprintf(
                '%1$s{display:grid !important;grid-template-columns:1fr !important}'
                . '@media (min-width:%2$dpx){%1$s{grid-template-columns:%3$s !important}}',
                $selector,
                $minWidth,
                $safe
            );
        } else {
            $css = sprintf(
                '%s{display:grid !important;grid-template-columns:%s !important}',
                $selector,
                $safe
            );
        }
        $style = LiteralField::create($this->gridTemplateStyleName, '<style>' . $css . '</style>');
        $style->addExtraClass('ss-grid-style');
        $this->getChildren()->unshift($style);

        return $this;
    }

    /**
     * The raw grid-template-columns value set via setGridTemplate(), or null for the 12-col model.
     */
    public function getGridTemplate(): ?string
    {
        return $this->gridTemplate;
    }

    /**
     * Whitelist the characters a grid-template-columns value legitimately needs. This prevents the
     * value from breaking out of the injected <style> rule (no `<`, `>`, `{`, `}`, `;`, `:` or quotes).
     */
    protected function sanitiseTemplate(string $template): string
    {
        $clean = preg_replace('/[^A-Za-z0-9%.,()\-\s]/', '', $template) ?? '';
        return trim(preg_replace('/\s+/', ' ', $clean) ?? '');
    }

    /**
     * Map a Bootstrap breakpoint name to its min-width in px (admin's sm = 544, not Bootstrap's 576).
     * 'xs'/'' (or an unknown name) = 0 → always-on, no media query, no collapse.
     */
    protected function breakpointMinWidth(string $breakpoint): int
    {
        return match (strtolower(trim($breakpoint))) {
            'sm' => 544,
            'md' => 768,
            'lg' => 992,
            'xl' => 1200,
            'xxl' => 1400,
            default => 0,
        };
    }

    /**
     * @param int|array<string,int> $span
     */
    protected function applyColumnClasses(FormField $field, int|array $span): void
    {
        $classes = [];
        if (is_array($span)) {
            foreach ($span as $breakpoint => $cols) {
                $classes[] = $this->columnClass((string) $breakpoint, (int) $cols);
            }
        } else {
            $classes[] = $this->columnClass($this->defaultBreakpoint, $span);
        }
        $field->addExtraClass(implode(' ', array_filter($classes)));
    }

    protected function columnClass(string $breakpoint, int $cols): string
    {
        $breakpoint = strtolower($breakpoint);
        // 'xs' is Bootstrap's base (no infix): col-6, not col-xs-6.
        return ($breakpoint === '' || $breakpoint === 'xs')
            ? "col-{$cols}"
            : "col-{$breakpoint}-{$cols}";
    }
}
