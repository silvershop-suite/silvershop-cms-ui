# SilverShop CMS UI

Neutral, structural **CMS-form layout primitives** for the Silverstripe 6 admin, built for the
SilverShop suite but **shop-agnostic** (no `silvershop/core` dependency — usable on any Silverstripe 6
project).

It ships two fields:

- **`GridLayoutField`** — lays a `CompositeField`'s children out on the admin's Bootstrap 5 grid
  (responsive 12-column spans), with an opt-in **full-page-width** mode and an **fr-template escape
  hatch** for exact, non-12-column ratios.
- **`PanelField`** — a native-blend **card** (`.ss-panel`) with a titled head, an optional
  header-actions slot, and a body that holds the real fields (with an edge-to-edge `flush` variant).

So a `ModelAdmin` edit form can be laid out as a responsive grid of panels instead of flat tab sprawl.
The primitives are deliberately minimal and **blend with the stock admin** (including its dark mode);
opinionated skins layer on top in the consuming module.

![Grid of panels, full width](docs/images/cms-ui-light.png)

## Requirements

- PHP `^8.3`
- `silverstripe/framework` `^6.0`
- `silverstripe/admin` `^3.0`

## Install

```bash
composer require silvershop-suite/cms-ui
```

The stylesheet is injected into the CMS automatically (via `LeftAndMain.extra_requirements_css`). It is
loaded CMS-wide but **inert by default** — every rule is gated behind an opt-in class (`.ss-grid` /
`.ss-panel*`), so a form that doesn't use the primitives is untouched.

## `GridLayoutField`

```php
use SilverShop\CmsUi\Forms\GridLayoutField;
use SilverStripe\Forms\TextField;

$grid = GridLayoutField::create('Address', [
    TextField::create('Street', 'Street'),
    TextField::create('Number', 'Nr'),
])->setColumns(['Street' => 8, 'Number' => 4]);   // 12-col spans
```

Spans may be a bare int (span at the default breakpoint, `md`) or a per-breakpoint array:

```php
GridLayoutField::create('Row')
    ->addColumn(TextField::create('First'), 6)                 // col-md-6
    ->addColumn(TextField::create('Last'),  ['md' => 6, 'lg' => 4]);
```

### Full page width

By default a grid keeps the admin's readable field width (~58%). `enableFullWidth()` breaks it out to
the **whole form row** — the headline feature for wide, column-heavy layouts:

```php
GridLayoutField::create('Wide', [...])->enableFullWidth();
```

Under the hood this is pure CSS: the admin caps every field's control wrapper
(`.form__field-holder { max-width: 895px }`, ~58% of the row). `enableFullWidth()` adds `.ss-grid-full`,
whose single rule reclaims the width:

```css
.CompositeField.ss-grid.ss-grid-full > .form__field-holder {
    width: 100% !important;
    max-width: none !important;
    flex: 1 1 100% !important;
}
```

No `LeftAndMain` rebuild and no custom admin JavaScript is involved — just CSS on opt-in class hooks.

### Exact ratios — the `setGridTemplate()` escape hatch

The 12-column model can't hit every ratio (e.g. a 1.7 : 1 split). `setGridTemplate()` takes an arbitrary
CSS `grid-template-columns` value and uses it verbatim, bypassing the 12-column math:

```php
GridLayoutField::create('Split', [$main, $aside])->setGridTemplate('1.7fr 1fr');
```

The value is carried as a scoped `<style>` rule injected through a hidden `LiteralField` child, so it
travels with the field's own markup and targets its inner `.form__field-holder` wrapper directly — no
per-instance inline attribute needed. A per-instance class scopes the rule to this grid alone, and the
value is sanitised before emission. Pass `null` to clear it and return to the 12-column model. Classes
don't inherit, so a **nested** `GridLayoutField`
keeps the default 12-column tracks.

### Other knobs

| Method | What it does |
| --- | --- |
| `setGutter('ss-gutter-0\|sm\|md\|lg')` | Column gap. Presets map to `--ss-gutter` (0 / 0.5em / 1.25em / 2em). A Bootstrap `g-*` class also works. |
| `setBreakpoint('xs')` | The breakpoint used for bare-int spans (default `md`). `xs` = always-on columns. |
| `setColumnCount(n)` | Inherited from `CompositeField`; cms-ui revives the admin's dropped `.multicolumn` CSS so `n` equal columns render again. |

On phones (`≤ 767.98px`) every grid collapses to a single stacked column that reads like a normal list
of fields.

## `PanelField`

```php
use SilverShop\CmsUi\Forms\PanelField;

PanelField::create('Summary', 'Order summary', [
    $someField,
    $anotherField,
])
    ->setCount('12 items')                                   // muted count after the title
    ->setActions('<a class="btn btn-secondary">Edit</a>')    // right-aligned header slot
    ->setFlush();                                            // body = edge-to-edge (tables, timelines)
```

| Method | What it does |
| --- | --- |
| `setTitle($title)` / constructor title | The head heading. |
| `setCount($text)` | A muted count/subtitle next to the title. |
| `setActions($htmlOrField)` | The right-aligned header-actions slot (raw HTML or a `FormField`). For v1 these are presentational; wiring interactive `FormAction`s into the head is a consumer concern. |
| `setFlush($bool = true)` | Body becomes edge-to-edge (`.ss-panel__body--flush`) — for a `GridField` / table / timeline. |
| `setHeadingLevel($n)` | Heading level for the title (2–6, default 2). |
| `getBody()` | The body composite — add fields to it. |

Fields passed to the constructor (or via `push()`) go into the **body**; they render and save exactly
as normal schema fields.

### Composition

The intended shape is a full-width grid of panels:

```php
GridLayoutField::create('Layout', [
    PanelField::create('Main', 'Order', [ /* real fields, or a nested GridLayoutField */ ]),
    PanelField::create('Side', 'Customer', [ /* … */ ]),
])
    ->enableFullWidth()
    ->setGridTemplate('1.7fr 1fr');   // or ->setColumns([...]) for a 12-col ratio
```

## Styling & theming

cms-ui ships **one small stylesheet** and styles **only its own classes** (`.ss-panel*`, `.ss-grid`,
the `.col-*` mapping) — never admin-internal selectors, and never the admin's own bare `.panel`
container. Tokens alias the admin's `--bs-*` custom properties, so the primitives blend with the native
admin and inherit its **dark mode** for free:

```css
:root {
    --ss-gutter: 1.25em;
    --ss-panel-bg: var(--bs-body-bg, #fff);
    --ss-panel-border: var(--bs-border-color, #ced5e1);
    --ss-panel-radius: var(--bs-border-radius, 0.23em);
    --ss-panel-shadow: var(--bs-box-shadow-sm, 0 1px 2px rgba(0, 0, 0, 0.08));
    --ss-panel-title-color: var(--bs-body-color, #43536d);
    --ss-panel-muted: var(--bs-secondary-color, #74809a);
    /* … */
}
```

A consumer skin overrides just these tokens (and/or styles its own component classes scoped to its
section) to apply an opinionated look. Units are `em` throughout — the admin's startup theme sets the
root font-size to 62.5% (10px), which makes `rem` render too small inside a module stylesheet.

Because every colour is aliased to a `--bs-*` token, the panels track the admin's theme automatically —
here with a dark `--bs-*` palette applied, the card surfaces, borders and text follow (the field inputs
are the admin's own surfaces):

![Panels following a dark `--bs-*` palette](docs/images/cms-ui-dark.png)

## Accessibility

A panel's head renders a real heading (`<h2>`/`<h3>`), giving it a name in the CMS heading outline that
assistive tech can navigate to — the heading is the panel's accessible anchor.

## Scope

- **v1 is CSS-only** — no JavaScript, no `.ss` templates, no `LeftAndMain` extension.
- **Deferred to v2:** collapsible panels (`PanelField->setCollapsible()`, needs the first JS asset).

## Licence

BSD-3-Clause.
