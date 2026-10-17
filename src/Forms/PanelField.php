<?php

namespace SilverShop\CmsUi\Forms;

use SilverStripe\Core\Convert;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\LiteralField;

/**
 * A card-style CompositeField for the Silverstripe 6 admin edit form: a titled "panel" with an
 * optional header-actions slot and a body that holds the real child fields.
 *
 * The card is composed in PHP (no `.ss` template) into a fixed, predictable shape — a head (a
 * LiteralField of raw heading HTML) and a body (a nested CompositeField that holds the real fields) —
 * and `client/css/cms-ui.css` styles the `.ss-panel*` class hooks. The rendered DOM is:
 *
 *   .CompositeField.field.ss-panel                 (this field; carries the card chrome)
 *    └ .form__field-holder
 *       ├ .field.ss-panel__head-holder             (LiteralField holder; a structural reset)
 *       │  └ .ss-panel__head                       (the visible bar: <h2> title + optional .ss-panel__actions)
 *       └ .CompositeField.field.ss-panel__body     (nested composite; real fields render inside it)
 *          └ .form__field-holder
 *             └ …child fields…
 *
 *   PanelField::create('Summary', 'Order summary', [ $child, ... ])
 *       ->setActions('<button class="btn btn-primary">Edit</button>')  // header slot (raw HTML / FormField)
 *       ->setCount('12 items')                                         // muted count after the title
 *       ->setFlush();                                                  // body = edge-to-edge (no padding)
 *
 * Note on accessibility: the head renders a real heading (`<h2>`/`<h3>`), which gives the panel a name
 * in the document/heading outline that assistive tech can navigate to. (Collapsible panels — which need
 * the first JS asset — are deferred to v2.)
 */
class PanelField extends CompositeField
{
    protected ?string $panelTitle = null;

    protected ?string $panelCount = null;

    protected string $panelActions = '';

    protected int $headingLevel = 2;

    protected bool $flush = false;

    protected LiteralField $headField;

    protected CompositeField $bodyField;

    /**
     * Flexible constructor, mirroring GridLayoutField:
     *   PanelField::create('Name', 'Title', [children])
     *   PanelField::create('Name', [children])
     *   PanelField::create([children])
     *
     * @param string|array|FieldList|FormField|null $nameOrChildren
     * @param string|array|FieldList|FormField|null $titleOrChildren
     * @param array|FieldList|FormField|null        $children
     */
    public function __construct($nameOrChildren = null, $titleOrChildren = null, $children = null)
    {
        $name = null;
        $title = null;
        $kids = null;

        if (is_string($nameOrChildren)) {
            $name = $nameOrChildren;
            if (is_string($titleOrChildren)) {
                $title = $titleOrChildren;
                $kids = $children;
            } else {
                $kids = $titleOrChildren;
            }
        } else {
            $kids = $nameOrChildren;
        }

        $baseName = $name ?: 'Panel';

        // Body holds the real child fields; it is a nested composite so its data fields collate/save
        // normally (CompositeField::collateDataFields recurses).
        $this->bodyField = CompositeField::create($this->normaliseChildren($kids));
        $this->bodyField->setName($baseName . 'Body');
        $this->bodyField->addExtraClass('ss-panel__body');

        // Head is raw HTML (heading + actions slot), rebuilt by the setters below. The class goes on
        // the LiteralField's holder (a structural reset); the VISIBLE bar is the inner .ss-panel__head
        // div emitted in rebuildHead(), so the styling holds whether or not the schema wraps the
        // literal in a field holder.
        $this->headField = LiteralField::create($baseName . 'Head', '');
        $this->headField->addExtraClass('ss-panel__head-holder');

        parent::__construct(FieldList::create([$this->headField, $this->bodyField]));

        if ($name !== null) {
            $this->setName($name);
        }
        $this->panelTitle = $title;
        $this->addExtraClass('ss-panel');
        $this->rebuildHead();
    }

    /**
     * @param array|FieldList|FormField|null $kids
     */
    protected function normaliseChildren($kids): FieldList
    {
        if ($kids instanceof FieldList) {
            return $kids;
        }
        return FieldList::create($kids === null ? [] : (is_array($kids) ? $kids : [$kids]));
    }

    /**
     * Set the panel's title (the head heading).
     *
     * @param string|null $title
     */
    public function setTitle($title)
    {
        parent::setTitle($title);
        $this->panelTitle = $title === null ? null : (string) $title;
        $this->rebuildHead();
        return $this;
    }

    /**
     * A muted count/subtitle shown next to the title (e.g. "12 items").
     */
    public function setCount(?string $count): static
    {
        $this->panelCount = $count;
        $this->rebuildHead();
        return $this;
    }

    /**
     * The right-aligned header-actions slot. Accepts raw HTML or a FormField (rendered to HTML).
     * For v1 these are presentational markup; wiring real, interactive FormActions into the head is a
     * consumer concern.
     *
     * @param string|FormField|null $actions
     */
    public function setActions($actions): static
    {
        if ($actions instanceof FormField) {
            $actions = (string) $actions->forTemplate();
        }
        $this->panelActions = $actions === null ? '' : (string) $actions;
        $this->rebuildHead();
        return $this;
    }

    /**
     * The heading level used for the title (2–6; default 2) — keeps the CMS heading outline sane when
     * panels nest under the form's own headings.
     */
    public function setHeadingLevel(int $level): static
    {
        $this->headingLevel = max(2, min(6, $level));
        $this->rebuildHead();
        return $this;
    }

    /**
     * Make the body edge-to-edge (no padding) — for a GridField / table / timeline that should bleed
     * to the card edges. Adds `.ss-panel__body--flush`.
     */
    public function setFlush(bool $flush = true): static
    {
        $this->flush = $flush;
        if ($flush) {
            $this->bodyField->addExtraClass('ss-panel__body--flush');
        } else {
            $this->bodyField->removeExtraClass('ss-panel__body--flush');
        }
        return $this;
    }

    public function getFlush(): bool
    {
        return $this->flush;
    }

    /**
     * The body composite — add fields to this to populate the panel.
     */
    public function getBody(): CompositeField
    {
        return $this->bodyField;
    }

    /**
     * The head LiteralField.
     */
    public function getHead(): LiteralField
    {
        return $this->headField;
    }

    /**
     * Child fields are pushed into the body (not alongside head/body), so the card shape is preserved.
     *
     * @param FormField $field
     */
    public function push(FormField $field)
    {
        $this->bodyField->push($field);
    }

    /**
     * Look in the body (where the real fields live) first, then fall back to the direct children.
     *
     * @param string $name
     */
    public function fieldByName($name)
    {
        return $this->bodyField->fieldByName($name) ?? parent::fieldByName($name);
    }

    /**
     * (Re)build the head's raw HTML from the current title / count / actions.
     */
    protected function rebuildHead(): void
    {
        $hasTitle = $this->panelTitle !== null && $this->panelTitle !== '';
        $hasCount = $this->panelCount !== null && $this->panelCount !== '';
        $hasActions = $this->panelActions !== '';

        if (!$hasTitle && !$hasCount && !$hasActions) {
            $this->headField->setContent('');
            return;
        }

        $level = $this->headingLevel;
        $titleId = Convert::raw2att(($this->getName() ?: 'Panel') . '_title');

        // A head with actions/count but no title still needs an accessible name — fall back to a
        // visually-hidden translatable heading.
        $titleText = $hasTitle ? (string) $this->panelTitle : _t(self::class . '.DEFAULT_TITLE', 'Panel');
        $titleClass = 'ss-panel__title' . ($hasTitle ? '' : ' ss-panel__title--sr');

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

        $inner = '<div class="ss-panel__heading">' . $heading . '</div>';
        if ($hasActions) {
            $inner .= '<div class="ss-panel__actions">' . $this->panelActions . '</div>';
        }

        $this->headField->setContent('<div class="ss-panel__head">' . $inner . '</div>');
    }
}
