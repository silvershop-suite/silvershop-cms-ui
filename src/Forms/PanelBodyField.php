<?php

namespace SilverShop\CmsUi\Forms;

use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\LiteralField;

/**
 * The body part of a {@link PanelField}: a nested CompositeField (class `ss-panel__body`) that holds the
 * panel's real child fields and owns the body-only display options — a flush, edge-to-edge body (for a
 * GridField / table / timeline) and a capped, scrollable height. Split out of PanelField so each panel
 * part is its own primitive (PanelField is the assembler; this is the body). `client/css/cms-ui.css`
 * styles `.ss-panel__body*`.
 *
 * Its data fields collate/save normally — CompositeField::collateDataFields recurses — so a panel body is
 * transparent to the form.
 */
class PanelBodyField extends CompositeField
{
    protected bool $flush = false;

    /** Per-instance class + injected-<style> name for setMaxHeight() (scrollable body). */
    protected string $scrollClass = '';

    protected string $scrollStyleName = '';

    /**
     * @param string|array|FieldList|FormField|null $nameOrChildren
     * @param array|FieldList|FormField|null        $children
     */
    public function __construct($nameOrChildren = null, $children = null)
    {
        $name = null;
        $kids = null;
        if (is_string($nameOrChildren)) {
            $name = $nameOrChildren;
            $kids = $children;
        } else {
            $kids = $nameOrChildren;
        }

        parent::__construct($this->normaliseChildren($kids));
        $this->setName($name ?: 'PanelBody');
        $this->addExtraClass('ss-panel__body');
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
     * Make the body edge-to-edge (no padding) — for a GridField / table / timeline that should bleed to
     * the card edges. Adds `.ss-panel__body--flush`.
     */
    public function setFlush(bool $flush = true): static
    {
        $this->flush = $flush;
        if ($flush) {
            $this->addExtraClass('ss-panel__body--flush');
        } else {
            $this->removeExtraClass('ss-panel__body--flush');
        }
        return $this;
    }

    public function getFlush(): bool
    {
        return $this->flush;
    }

    /**
     * Cap the body height and scroll on overflow: the panel head stays fixed and the body gets a vertical
     * scrollbar once its content exceeds $maxHeight (any CSS length — '20em', '320px',
     * 'calc(100vh - 20em)'). Pass null or '' to clear. Implemented like GridLayoutField::setGridTemplate():
     * a per-instance class plus a scoped <style> injected as a hidden child, so there is no reliance on
     * inline style attributes. Adds `.ss-panel__body--scroll`.
     */
    public function setMaxHeight(?string $maxHeight): static
    {
        // Clear any previously applied cap (its injected <style> child + per-instance class).
        if ($this->scrollStyleName !== '') {
            $this->getChildren()->removeByName($this->scrollStyleName);
        }
        if ($this->scrollClass !== '') {
            $this->removeExtraClass($this->scrollClass);
        }
        $this->removeExtraClass('ss-panel__body--scroll');
        $this->scrollClass = '';
        $this->scrollStyleName = '';

        $maxHeight = $maxHeight === null ? '' : trim($maxHeight);
        if ($maxHeight === '') {
            return $this;
        }
        $safe = $this->sanitiseLength($maxHeight);
        if ($safe === '') {
            return $this;
        }

        $hash = substr(md5($safe), 0, 10);
        $this->scrollClass = 'ss-panel-maxh-' . $hash;
        $this->scrollStyleName = 'SSPanelMaxH_' . $hash;
        $this->addExtraClass('ss-panel__body--scroll');
        $this->addExtraClass($this->scrollClass);

        // The body's inner holder (which carries the fields + padding) scrolls; capping it keeps the
        // scrollbar inside the card's rounded corners (the card clips with overflow:hidden).
        $selector = sprintf(
            '.CompositeField.ss-panel__body.%s > .form__field-holder',
            $this->scrollClass
        );
        // overflow-x:hidden is required: setting overflow-y:auto alone makes the CSS spec compute overflow-x
        // from visible to auto, so the vertical scrollbar's width triggers a spurious horizontal scrollbar.
        $css = sprintf(
            '%s{max-height:%s !important;overflow-y:auto !important;overflow-x:hidden !important}',
            $selector,
            $safe
        );
        $style = LiteralField::create($this->scrollStyleName, '<style>' . $css . '</style>');
        $style->addExtraClass('ss-panel__style');
        $this->getChildren()->unshift($style);

        return $this;
    }

    /**
     * Whitelist the characters a CSS length / calc() legitimately needs, so the value can't break out of
     * the injected <style> rule (no `<`, `>`, `{`, `}`, `;`, `:` or quotes).
     */
    protected function sanitiseLength(string $value): string
    {
        $clean = preg_replace('/[^A-Za-z0-9%.,()\-\s]/', '', $value) ?? '';
        return trim(preg_replace('/\s+/', ' ', $clean) ?? '');
    }
}
