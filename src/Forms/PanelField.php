<?php

namespace SilverShop\CmsUi\Forms;

use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;

/**
 * A card-style CompositeField for the Silverstripe 6 admin edit form: a titled "panel" assembled from
 * separate primitives — a {@link PanelHeaderField} (the flex header bar: icon + title + count on the left,
 * action buttons on the right) and a {@link PanelBodyField} (the real fields; owns flush / scrollable
 * height). PanelField is the thin assembler and the stable facade; `client/css/cms-ui.css` styles the
 * `.ss-panel*` hooks. (A PanelFooterField is a planned third part — not built until a consumer needs it.)
 *
 * The rendered DOM (the admin wraps a composite's children one level deep in `.form__field-holder`):
 *
 *   .CompositeField.field.ss-panel                 (this field; the card chrome)
 *    └ .form__field-holder                         (stacks head above body)
 *       ├ .CompositeField.ss-panel__head           (header bar — heading + action fields)
 *       └ .CompositeField.ss-panel__body           (body — the real fields)
 *
 *   PanelField::create('Summary', 'Order summary', [ $child, ... ])
 *       ->setIcon('receipt')                                  // head glyph
 *       ->setCount('12 items')                                // muted count after the title
 *       ->setActions('<span class="…">Paid</span>')           // static head HTML (e.g. status pills)
 *       ->addAction($editButton)                              // a live interactive action (button / routed GridField)
 *       ->setFlush()                                          // body edge-to-edge (no padding)
 *       ->setBodyMaxHeight('18em');                           // capped, scrollable body
 *
 * Accessibility: the head renders a real heading (`<h2>`/`<h3>`), naming the panel in the heading outline.
 */
class PanelField extends CompositeField
{
    protected PanelHeaderField $header;

    protected PanelBodyField $body;

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

        $this->header = PanelHeaderField::create($baseName . 'Head', $title);
        $this->body = PanelBodyField::create($baseName . 'Body', $this->normaliseChildren($kids));

        parent::__construct(FieldList::create([$this->header, $this->body]));

        if ($name !== null) {
            $this->setName($name);
        }
        $this->addExtraClass('ss-panel');
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

    /** The header part. */
    public function getHeader(): PanelHeaderField
    {
        return $this->header;
    }

    /** The body part — add fields to this to populate the panel. */
    public function getBody(): PanelBodyField
    {
        return $this->body;
    }

    /** Back-compat alias for getHeader(). */
    public function getHead(): PanelHeaderField
    {
        return $this->header;
    }

    // ---- Head (delegated to the header) --------------------------------------------------------------

    /**
     * Set the panel's title (the head heading).
     *
     * @param string|null $title
     */
    public function setTitle($title)
    {
        parent::setTitle($title);
        $this->header->setPanelTitle($title === null ? null : (string) $title);
        return $this;
    }

    public function setCount(?string $count): static
    {
        $this->header->setCount($count);
        return $this;
    }

    /** An ss-icon glyph name (without the `ss-icon-` prefix), e.g. 'truck', shown before the title. */
    public function setIcon(?string $icon): static
    {
        $this->header->setIcon($icon);
        return $this;
    }

    public function setHeadingLevel(int $level): static
    {
        $this->header->setHeadingLevel($level);
        return $this;
    }

    /**
     * Static raw-HTML head actions (e.g. status pills). Accepts raw HTML or a FormField (rendered to HTML).
     * For a live interactive action use addAction().
     *
     * @param string|FormField|null $actions
     */
    public function setActions($actions): static
    {
        if ($actions instanceof FormField) {
            $actions = (string) $actions->forTemplate();
        }
        $this->header->setActions($actions === null ? '' : (string) $actions);
        return $this;
    }

    /**
     * Add a live, interactive action field (a button, a routed GridField) to the head's right side. Several
     * may be added; they sit in a right-aligned row with identical look + spacing across every panel.
     */
    public function addAction(FormField $field): static
    {
        $this->header->addAction($field);
        return $this;
    }

    /**
     * Back-compat single-action setter: replace the live head actions with this one field (null clears).
     */
    public function setActionsField(?FormField $field): static
    {
        $this->header->clearActions();
        if ($field !== null) {
            $this->header->addAction($field);
        }
        return $this;
    }

    /** The first live action field, if any (back-compat). */
    public function getActionsField(): ?FormField
    {
        return $this->header->getActions()->first() ?: null;
    }

    /** All live action fields. */
    public function getActions(): FieldList
    {
        return $this->header->getActions();
    }

    // ---- Body (delegated to the body) ----------------------------------------------------------------

    /**
     * Make the body edge-to-edge (no padding) — for a GridField / table / timeline. Adds
     * `.ss-panel__body--flush`.
     */
    public function setFlush(bool $flush = true): static
    {
        $this->body->setFlush($flush);
        return $this;
    }

    public function getFlush(): bool
    {
        return $this->body->getFlush();
    }

    /**
     * Cap the body height and scroll on overflow (any CSS length; null/'' clears). Delegates to the body.
     */
    public function setBodyMaxHeight(?string $maxHeight): static
    {
        $this->body->setMaxHeight($maxHeight);
        return $this;
    }

    /**
     * Remove the card elevation (drop-shadow) — for a sub-section panel that should read flat rather than as
     * a floating card. Adds `.ss-panel--flat`. A panel nested inside another panel's body is flattened
     * automatically; use this to force a flat panel anywhere.
     */
    public function setFlat(bool $flat = true): static
    {
        if ($flat) {
            $this->addExtraClass('ss-panel--flat');
        } else {
            $this->removeExtraClass('ss-panel--flat');
        }
        return $this;
    }

    /**
     * Child fields are pushed into the body (not alongside head/body), so the card shape is preserved.
     *
     * @param FormField $field
     */
    public function push(FormField $field)
    {
        $this->body->push($field);
    }

    /**
     * Look in the body (where the real fields live) first, then fall back to the direct children.
     *
     * @param string $name
     */
    public function fieldByName($name)
    {
        return $this->body->fieldByName($name) ?? parent::fieldByName($name);
    }
}
