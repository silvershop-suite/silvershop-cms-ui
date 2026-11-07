<?php

namespace SilverShop\CmsUi\Forms;

use SilverStripe\Core\Convert;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\LiteralField;

/**
 * The head part of a {@link PanelField}: a CompositeField rendered as the card's flex header bar (class
 * `ss-panel__head`). It holds the heading (an optional icon + the title + an optional muted count) on the
 * left and, grouped on the right, an actions area: raw-HTML actions (status pills) and/or live action
 * buttons.
 *
 * Crucially the live actions are REAL child {@link FormField}s, not an HTML overlay: because the header is a
 * composite and the panel a composite, an action field is collated into the form (CompositeField::
 * collateDataFields recurses), so an interactive action — e.g. a routed GridField / HasOneButtonField whose
 * detail form must resolve — works, while the header's own flexbox does the placement. No absolute
 * positioning, no per-consumer CSS: `client/css/cms-ui.css` gives every panel's actions the same look +
 * position via `.ss-panel__action`.
 *
 * The bar is laid out with `justify-content: space-between` over exactly two flex children — the heading and
 * a single actions container — so it is robust to whether the admin wraps a child field in a holder:
 *
 *   .CompositeField.ss-panel__head
 *    └ .form__field-holder                      (flex bar: heading ↔ actions)
 *       ├ (heading literal → .ss-panel__heading: icon + <h2> + count)
 *       └ .CompositeField.ss-panel__actions      (present only when there are actions)
 *          └ .form__field-holder                 (flex row: static pills + action buttons)
 *             ├ (raw-HTML actions — setActions())
 *             └ .field…                          (live action buttons — addAction())
 */
class PanelHeaderField extends CompositeField
{
    protected ?string $panelTitle = null;

    protected ?string $panelCount = null;

    /** An ss-icon glyph name (without the `ss-icon-` prefix), e.g. 'truck'. */
    protected ?string $icon = null;

    protected int $headingLevel = 2;

    /** Raw-HTML actions (status pills etc.) rendered into the actions area via setActions(). */
    protected string $staticActions = '';

    protected LiteralField $headingField;

    /** The actions container (lazily created): a composite holding the static-HTML + the live buttons. */
    protected ?CompositeField $actionsContainer = null;

    protected ?LiteralField $staticActionsField = null;

    /** The live, interactive action fields (buttons) added via addAction(). */
    protected FieldList $actionFields;

    protected string $baseName;

    public function __construct(?string $name = null, ?string $title = null)
    {
        $this->baseName = $name ?: 'PanelHead';
        $this->panelTitle = $title;
        $this->actionFields = FieldList::create();

        $this->headingField = LiteralField::create($this->baseName . 'Heading', '');
        $this->headingField->addExtraClass('ss-panel__heading-holder');

        parent::__construct(FieldList::create([$this->headingField]));
        $this->setName($this->baseName);
        $this->addExtraClass('ss-panel__head');
        $this->rebuild();
    }

    public function setPanelTitle(?string $title): static
    {
        $this->panelTitle = $title;
        $this->rebuild();
        return $this;
    }

    public function getPanelTitle(): ?string
    {
        return $this->panelTitle;
    }

    /** A muted count/subtitle shown next to the title (e.g. "12 items"). */
    public function setCount(?string $count): static
    {
        $this->panelCount = $count;
        $this->rebuild();
        return $this;
    }

    /** An ss-icon glyph name (without the `ss-icon-` prefix), e.g. 'truck'. Pass null to clear. */
    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;
        $this->rebuild();
        return $this;
    }

    /** Heading level 2–6 (default 2) — keeps the CMS heading outline sane when panels nest. */
    public function setHeadingLevel(int $level): static
    {
        $this->headingLevel = max(2, min(6, $level));
        $this->rebuild();
        return $this;
    }

    /**
     * Raw-HTML actions for the head's right side (e.g. status pills) — presentational markup, not an
     * interactive field. For a live control (a button, a routed GridField) use addAction(). Pass '' to clear.
     */
    public function setActions(string $html): static
    {
        $this->staticActions = $html;
        $this->rebuild();
        return $this;
    }

    /**
     * Add a live, interactive action field (a button) to the head's right side. The field is a real child
     * (collated into the form, so its own routes resolve); cms-ui styles + positions it. Mark the button
     * markup with class `ss-panel__action` for the standard panel action face. Several may be added; they
     * sit in a right-aligned row.
     */
    public function addAction(FormField $field): static
    {
        $field->addExtraClass('ss-panel__action-field');
        $this->actionFields->push($field);
        $this->ensureActionsContainer()->push($field);
        $this->rebuild();
        return $this;
    }

    /** Remove all live action fields (keeps any static-HTML actions). */
    public function clearActions(): static
    {
        if ($this->actionsContainer !== null) {
            foreach ($this->actionFields as $field) {
                $this->actionsContainer->getChildren()->remove($field);
            }
        }
        $this->actionFields = FieldList::create();
        $this->rebuild();
        return $this;
    }

    /** The live action fields. */
    public function getActions(): FieldList
    {
        return $this->actionFields;
    }

    /** The actions container composite, if any actions have been added. */
    public function getActionsContainer(): ?CompositeField
    {
        return $this->actionsContainer;
    }

    /** The heading LiteralField (icon + title + count). */
    public function getHeadingField(): LiteralField
    {
        return $this->headingField;
    }

    protected function hasActions(): bool
    {
        return $this->staticActions !== '' || $this->actionFields->count() > 0;
    }

    /** Create the actions container (once) and append it after the heading. */
    protected function ensureActionsContainer(): CompositeField
    {
        if ($this->actionsContainer === null) {
            $this->actionsContainer = CompositeField::create();
            $this->actionsContainer->setName($this->baseName . 'Actions');
            $this->actionsContainer->addExtraClass('ss-panel__actions');
            $this->getChildren()->push($this->actionsContainer);
        }
        return $this->actionsContainer;
    }

    /** Drop the actions container entirely (no static + no live actions left). */
    protected function removeActionsContainer(): void
    {
        if ($this->actionsContainer !== null) {
            $this->getChildren()->remove($this->actionsContainer);
            $this->actionsContainer = null;
            $this->staticActionsField = null;
        }
    }

    /** (Re)build the heading HTML + the static-actions carrier, and the empty/actions modifier classes. */
    protected function rebuild(): void
    {
        $hasTitle = $this->panelTitle !== null && $this->panelTitle !== '';
        $hasCount = $this->panelCount !== null && $this->panelCount !== '';
        $hasActions = $this->hasActions();

        // A fully empty head (no title/count/icon/actions) collapses to no bar (headerless panel).
        $empty = !$hasTitle && !$hasCount && $this->icon === null && !$hasActions;
        if ($empty) {
            $this->addExtraClass('ss-panel__head--empty');
        } else {
            $this->removeExtraClass('ss-panel__head--empty');
        }
        if ($hasActions) {
            $this->addExtraClass('ss-panel__head--actions');
        } else {
            $this->removeExtraClass('ss-panel__head--actions');
        }

        $this->headingField->setContent($empty ? '' : $this->buildHeadingHtml($hasTitle, $hasCount));
        $this->syncStaticActions();

        // Tidy up: if nothing is left in the actions area, drop the container.
        if (!$hasActions) {
            $this->removeActionsContainer();
        }
    }

    protected function buildHeadingHtml(bool $hasTitle, bool $hasCount): string
    {
        $level = $this->headingLevel;
        $titleId = Convert::raw2att($this->baseName . '_title');

        // A head with actions/count but no title still needs an accessible name — a visually-hidden heading.
        $titleText = $hasTitle ? (string) $this->panelTitle : _t(self::class . '.DEFAULT_TITLE', 'Panel');
        $titleClass = 'ss-panel__title' . ($hasTitle ? '' : ' ss-panel__title--sr');

        $iconHtml = $this->icon
            ? '<i class="ss-icon ss-icon-' . Convert::raw2att($this->icon) . ' ss-panel__icon" aria-hidden="true"></i>'
            : '';

        $heading = sprintf(
            '<h%1$d id="%2$s" class="%3$s">%4$s</h%1$d>',
            $level,
            $titleId,
            $titleClass,
            Convert::raw2xml($titleText)
        );
        if ($hasCount) {
            $heading .= '<span class="ss-panel__count">' . Convert::raw2xml((string) $this->panelCount) . '</span>';
        }

        return '<div class="ss-panel__heading">' . $iconHtml . $heading . '</div>';
    }

    /** Add / update / remove the raw-HTML actions carrier, kept first in the actions container. */
    protected function syncStaticActions(): void
    {
        if ($this->staticActions !== '') {
            $container = $this->ensureActionsContainer();
            if ($this->staticActionsField === null) {
                $this->staticActionsField = LiteralField::create($this->baseName . 'StaticActions', '');
                $this->staticActionsField->addExtraClass('ss-panel__actions-static');
                $container->getChildren()->unshift($this->staticActionsField);
            }
            $this->staticActionsField->setContent($this->staticActions);
        } elseif ($this->staticActionsField !== null && $this->actionsContainer !== null) {
            $this->actionsContainer->getChildren()->remove($this->staticActionsField);
            $this->staticActionsField = null;
        }
    }
}
