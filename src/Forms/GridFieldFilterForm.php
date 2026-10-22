<?php

namespace SilverShop\CmsUi\Forms;

use SilverStripe\Core\Convert;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\ORM\FieldType\DBHTMLText;

/**
 * An always-visible, multi-field list filter FORM that replaces Silverstripe 6's hidden search foldout
 * ({@link GridFieldFilterHeader}, the collapsed search-icon that expands a React-driven panel).
 *
 * It extends the native header and overrides ONLY {@link self::getHTMLFragments()} — the whole filter
 * engine is INHERITED verbatim (getActions / handleAction / getManipulatedData / initDefaultState /
 * getSearchContext / getSearchForm / canFilterAnyColumns / getURLHandlers), so search, sort, pagination
 * and GridState persistence keep working with NO custom JavaScript. The override renders the SearchContext's
 * scaffolded fields inline, pre-filled from the current filter state, each renamed to the
 * `filter[<GridName>][<FieldName>]` shape that the inherited handleAction() already reads, plus a Search and
 * a Reset button built as real {@link GridField_FormAction}s.
 *
 * Why this rides the stock engine with no JS: the two buttons are ordinary `.grid-field .action` buttons,
 * so the admin's generic gridfield entwine handler serialises the whole edit form (minus `.cms-content-filters`
 * / `.relation-search`) on click and POSTs it to the gridfield — carrying our named inputs with it — then
 * replaces the gridfield markup with the server response (our pre-filled form re-renders with the active
 * filters retained). So we add presentation only; the behaviour is the framework's own.
 *
 *   $config->removeComponentsByType(GridFieldFilterHeader::class);
 *   $config->addComponent(GridFieldFilterForm::create(), GridFieldDataColumns::class);
 *
 *   // Tune the layout / field set (all optional; v1 derives the fields from the model's searchable_fields):
 *   GridFieldFilterForm::create()
 *       ->setColumns(4)                              // equal n-column grid (default: auto, 1–4 by field count)
 *       ->setColumnSpans(['Email' => 6, 'Status' => 3])  // OR per-field 12-col spans (reuses the .ss-grid col-* map)
 *       ->setRestrictFields(['Reference', 'Name', 'Email'])  // whitelist which searchable fields to show
 *       ->setTitle('Filter');                        // optional head label (default: no head, just a filter bar)
 *
 * The markup is a plain `.ss-filter-form` block (NOT a nested <form> — the inputs belong to the surrounding
 * CMS edit form) styled by client/css/cms-ui.css; it blends with the stock admin and inherits its dark mode.
 */
class GridFieldFilterForm extends GridFieldFilterHeader
{
    /**
     * Equal column count for the field grid (sets --ss-filter-cols). Null = auto (derived from the field
     * count, capped at 4). Ignored when per-field spans are set via {@link self::setColumnSpans()}.
     */
    protected ?int $columns = null;

    /**
     * Per-field 12-column spans: FieldName => span (1–12). When non-empty the grid switches to a 12-track
     * model and each field cell gets a `.col-md-<span>` class (reusing the existing `.ss-grid .col-*` map);
     * a field not listed falls back to an even span computed from the visible field count.
     *
     * @var array<string,int>
     */
    protected array $columnSpans = [];

    /**
     * An explicit field list to render, overriding the scaffolded SearchContext fields. Field names are the
     * raw search keys (e.g. `Email`) — they are wrapped to `filter[<GridName>][<name>]` at render time.
     */
    protected ?FieldList $fields = null;

    /**
     * Whitelist of searchable field names to show (a subset of the scaffolded fields). Empty = show all.
     *
     * @var array<int,string>
     */
    protected array $restrictFields = [];

    /**
     * Optional head label shown above the fields (e.g. "Filter"). Null = no head, just the filter bar.
     */
    protected ?string $title = null;

    /**
     * When set, the Reset/Search buttons render as a grid CELL of this 12-col span (bottom-right, inline
     * with the fields — e.g. next to the last field) instead of a full-width actions bar below the grid,
     * which saves a row. Implies the 12-track grid. Null (default) = the separate actions bar.
     */
    protected ?int $actionsSpan = null;

    /**
     * Render the always-visible filter form into the `before` fragment (above the table). Returns null —
     * so the GridField renders no filter UI — when the list has nothing filterable, exactly like the native
     * header's guard.
     *
     * @param GridField $gridField
     * @return array<string,DBHTMLText>|null
     */
    public function getHTMLFragments($gridField)
    {
        if (!$this->canFilterAnyColumns($gridField)) {
            return null;
        }

        $fields = $this->buildFilterFields($gridField);
        if (!$fields->exists()) {
            return null;
        }

        return ['before' => $this->renderFilterForm($gridField, $fields)];
    }

    /**
     * Equal column count for the field grid (e.g. 4 → a four-column row). Pass null to restore auto.
     */
    public function setColumns(?int $columns): static
    {
        $this->columns = $columns !== null ? max(1, $columns) : null;
        return $this;
    }

    public function getColumns(): ?int
    {
        return $this->columns;
    }

    /**
     * Per-field 12-column spans: FieldName => span (1–12). Switches the grid to a 12-track model.
     *
     * @param array<string,int> $spans
     */
    public function setColumnSpans(array $spans): static
    {
        $clean = [];
        foreach ($spans as $name => $span) {
            $clean[(string) $name] = max(1, min(12, (int) $span));
        }
        $this->columnSpans = $clean;
        return $this;
    }

    /**
     * @return array<string,int>
     */
    public function getColumnSpans(): array
    {
        return $this->columnSpans;
    }

    /**
     * Supply an explicit field list, overriding the scaffolded SearchContext fields. Names are the raw
     * search keys; they are wrapped to the `filter[<GridName>][<name>]` shape at render time.
     */
    public function setFields(FieldList $fields): static
    {
        $this->fields = $fields;
        return $this;
    }

    public function getFields(): ?FieldList
    {
        return $this->fields;
    }

    /**
     * Whitelist which of the scaffolded searchable fields to show (by raw field name). Empty = show all.
     *
     * @param array<int,string> $names
     */
    public function setRestrictFields(array $names): static
    {
        $this->restrictFields = array_values(array_map('strval', $names));
        return $this;
    }

    /**
     * @return array<int,string>
     */
    public function getRestrictFields(): array
    {
        return $this->restrictFields;
    }

    /**
     * Optional head label shown above the fields. Pass null (the default) for a headless filter bar.
     */
    public function setTitle(?string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Render the Reset/Search buttons as a grid cell spanning $span of 12 columns (bottom-right, inline
     * with the fields) instead of a separate bar below — saves vertical space. Pass null to restore the bar.
     */
    public function setActionsSpan(?int $span): static
    {
        $this->actionsSpan = $span !== null ? max(1, min(12, $span)) : null;
        return $this;
    }

    public function getActionsSpan(): ?int
    {
        return $this->actionsSpan;
    }

    /**
     * Build the (cloned, safe-to-mutate) field list to render: the explicit {@link self::$fields} if set,
     * otherwise a clone of the SearchContext's scaffolded search fields — optionally narrowed to
     * {@link self::$restrictFields}. FieldList::__clone deep-clones each field, so mutating names/values
     * below never touches the shared SearchContext fields.
     */
    protected function buildFilterFields(GridField $gridField): FieldList
    {
        $source = $this->fields ?? $this->getSearchContext($gridField)->getSearchFields();
        $fields = clone $source;

        if (!empty($this->restrictFields)) {
            $keep = array_flip($this->restrictFields);
            // Collect first, then remove: mutating a FieldList mid-iteration skips elements.
            $drop = [];
            foreach ($fields as $field) {
                if (!isset($keep[$field->getName()])) {
                    $drop[] = $field->getName();
                }
            }
            foreach ($drop as $name) {
                $fields->removeByName($name);
            }
        }

        return $fields;
    }

    /**
     * Render the filter form HTML: an optional head, the field cells (each pre-filled from the current
     * filter state and renamed to `filter[<GridName>][<name>]`), and the Search + Reset buttons.
     */
    protected function renderFilterForm(GridField $gridField, FieldList $fields): DBHTMLText
    {
        $gridName = $gridField->getName();
        $state = $this->currentFilters($gridField);
        $visibleCount = $fields->count();

        $cells = '';
        foreach ($fields as $field) {
            $this->prepareField($field, $gridName, $state, $visibleCount);
            $cells .= (string) $field->FieldHolder();
        }

        $search = GridField_FormAction::create(
            $gridField,
            'filter',
            _t(self::class . '.SEARCH', 'Search'),
            'filter',
            []
        )->addExtraClass('btn btn-primary ss-filter-form__btn ss-filter-form__btn--search');

        $reset = GridField_FormAction::create(
            $gridField,
            'reset',
            _t(self::class . '.RESET', 'Reset'),
            'reset',
            []
        )->addExtraClass('btn btn-secondary ss-filter-form__btn ss-filter-form__btn--reset');

        $head = '';
        if ($this->title !== null && $this->title !== '') {
            $head = '<div class="ss-filter-form__head"><h3 class="ss-filter-form__title">'
                . Convert::raw2xml($this->title) . '</h3></div>';
        }

        $buttons = (string) $reset->Field() . (string) $search->Field();

        // Inline actions: append Reset/Search as a grid CELL pinned to the row's right edge (next to the last
        // field) to save a row; otherwise keep the full-width actions bar below the grid. The span is passed as
        // a CSS var (--ss-actions-span) and the cell is placed `span N / -1`, so its right edge always lands on
        // the grid's last column line regardless of how the preceding fields packed into the row.
        $actions = '';
        if ($this->actionsSpan !== null) {
            $cells .= '<div class="ss-filter-form__actions ss-filter-form__actions--inline" style="--ss-actions-span:'
                . $this->actionsSpan . '">' . $buttons . '</div>';
        } else {
            $actions = '<div class="ss-filter-form__actions">' . $buttons . '</div>';
        }

        $colVar = $this->resolveColumnCount($visibleCount);
        $grid = '<div class="ss-filter-form__grid ss-grid" style="--ss-filter-cols:' . $colVar . '">'
            . $cells . '</div>';

        $html = '<div class="ss-filter-form" role="search">' . $head . $grid . $actions . '</div>';

        return DBHTMLText::create()->setValue($html);
    }

    /**
     * Pre-fill a field from the active filter state and rewrite its name to `filter[<GridName>][<name>]`
     * (the shape the inherited handleAction() reads). Recurses into CompositeFields so scaffolded range
     * fields (from/to pairs) are rewritten per leaf. Applies a column-span class when configured.
     *
     * @param array<string,mixed> $state
     */
    protected function prepareField(FormField $field, string $gridName, array $state, int $visibleCount): void
    {
        if ($field instanceof CompositeField) {
            foreach ($field->getChildren() as $child) {
                $this->prepareField($child, $gridName, $state, $visibleCount);
            }
            $field->addExtraClass('ss-filter-form__field');
            $this->applySpan($field, $field->getName(), $visibleCount);
            return;
        }

        $raw = $field->getName();
        if (array_key_exists($raw, $state)) {
            $field->setValue($state[$raw]);
        }
        $field->setName($this->filterFieldName($gridName, $raw));
        $field->addExtraClass('ss-filter-form__field stacked');
        $this->applySpan($field, $raw, $visibleCount);
    }

    /**
     * Add the `.col-md-<span>` cell class when per-field spans are configured (12-track mode). A field with
     * no explicit span falls back to an even share of the 12 columns across the visible fields.
     */
    protected function applySpan(FormField $field, string $rawName, int $visibleCount): void
    {
        if (empty($this->columnSpans)) {
            return;
        }
        $fallback = max(1, min(12, (int) floor(12 / max(1, $visibleCount))));
        $span = $this->columnSpans[$rawName] ?? $fallback;
        $field->addExtraClass('col-md-' . $span);
    }

    /**
     * The field grid's column count: an explicit {@link self::setColumns()}, else 12 when per-field spans
     * are in play, else auto (the field count capped at 4).
     */
    protected function resolveColumnCount(int $visibleCount): int
    {
        if ($this->columns !== null) {
            return $this->columns;
        }
        if (!empty($this->columnSpans) || $this->actionsSpan !== null) {
            return 12;
        }
        return max(1, min(4, $visibleCount));
    }

    /**
     * Wrap a raw search field name into the `filter[<GridName>][<name>]` HTML name read by handleAction().
     */
    protected function filterFieldName(string $gridName, string $rawName): string
    {
        return sprintf('filter[%s][%s]', $gridName, $rawName);
    }

    /**
     * The active filter values from GridState (field name => value). Written by the inherited handleAction()
     * under the fixed `GridFieldFilterHeader` state key, so a subclass reads the same place.
     *
     * @return array<string,mixed>
     */
    protected function currentFilters(GridField $gridField): array
    {
        $columns = $gridField->State->GridFieldFilterHeader->Columns;
        $values = $columns ? $columns->toArray() : [];
        return is_array($values) ? $values : [];
    }
}
