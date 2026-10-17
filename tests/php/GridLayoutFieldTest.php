<?php

declare(strict_types=1);

namespace SilverShop\CmsUi\Tests;

use SilverShop\CmsUi\Forms\GridLayoutField;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;

class GridLayoutFieldTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testConstructorWithNameAndChildren(): void
    {
        $grid = GridLayoutField::create('Details', [
            TextField::create('FirstName'),
            TextField::create('LastName'),
        ]);

        $this->assertSame('Details', $grid->getName());
        $this->assertCount(2, $grid->getChildren());

        // The base hook + the default gutter preset land on the composite's own holder.
        $this->assertStringContainsString('ss-grid', $grid->extraClass());
        $this->assertStringContainsString('ss-gutter-md', $grid->extraClass());
    }

    public function testConstructorWithOnlyChildren(): void
    {
        $grid = GridLayoutField::create([TextField::create('A')]);
        $this->assertCount(1, $grid->getChildren());
        $this->assertStringContainsString('ss-grid', $grid->extraClass());
    }

    public function testSetColumnsStampsColClasses(): void
    {
        $grid = GridLayoutField::create('Row', [
            TextField::create('FirstName'),
            TextField::create('LastName'),
        ])->setColumns(['FirstName' => 6, 'LastName' => 6]);

        $this->assertStringContainsString('col-md-6', $grid->fieldByName('FirstName')->extraClass());
        $this->assertStringContainsString('col-md-6', $grid->fieldByName('LastName')->extraClass());
    }

    public function testAddColumnPushesAndStampsSpan(): void
    {
        $grid = GridLayoutField::create('Row')
            ->addColumn(TextField::create('Street'), ['md' => 8])
            ->addColumn(TextField::create('Nr'), ['md' => 4]);

        $this->assertCount(2, $grid->getChildren());
        $this->assertStringContainsString('col-md-8', $grid->fieldByName('Street')->extraClass());
        $this->assertStringContainsString('col-md-4', $grid->fieldByName('Nr')->extraClass());
    }

    public function testPerBreakpointSpanArray(): void
    {
        $grid = GridLayoutField::create('Row')
            ->addColumn(TextField::create('Wide'), ['md' => 8, 'lg' => 4]);

        $classes = $grid->fieldByName('Wide')->extraClass();
        $this->assertStringContainsString('col-md-8', $classes);
        $this->assertStringContainsString('col-lg-4', $classes);
    }

    public function testBareIntSpanUsesDefaultBreakpoint(): void
    {
        $grid = GridLayoutField::create('Row')->addColumn(TextField::create('A'), 6);
        $this->assertStringContainsString('col-md-6', $grid->fieldByName('A')->extraClass());
    }

    public function testSetBreakpointChangesBareIntInfix(): void
    {
        $grid = GridLayoutField::create('Row')
            ->setBreakpoint('xs')
            ->addColumn(TextField::create('A'), 6);

        // 'xs' is Bootstrap's base (no infix): col-6, not col-xs-6.
        $classes = $grid->fieldByName('A')->extraClass();
        $this->assertStringContainsString('col-6', $classes);
        $this->assertStringNotContainsString('col-xs-6', $classes);
    }

    public function testSetGutterSwapsClass(): void
    {
        $grid = GridLayoutField::create('Row');
        $this->assertStringContainsString('ss-gutter-md', $grid->extraClass());

        $grid->setGutter('ss-gutter-lg');
        $this->assertStringNotContainsString('ss-gutter-md', $grid->extraClass());
        $this->assertStringContainsString('ss-gutter-lg', $grid->extraClass());
    }

    public function testEnableFullWidthTogglesClass(): void
    {
        $grid = GridLayoutField::create('Row');
        $this->assertStringNotContainsString('ss-grid-full', $grid->extraClass());

        $grid->enableFullWidth();
        $this->assertStringContainsString('ss-grid-full', $grid->extraClass());

        $grid->enableFullWidth(false);
        $this->assertStringNotContainsString('ss-grid-full', $grid->extraClass());
    }

    public function testSetColumnCountFreebie(): void
    {
        // CompositeField::setColumnCount() is inherited; the .multicolumn hook (revived in cms-ui.css)
        // renders it. GridLayoutField keeps the full API, including this.
        $grid = GridLayoutField::create('Row', [TextField::create('A'), TextField::create('B')]);
        $grid->setColumnCount(2);

        $this->assertSame(2, $grid->getColumnCount());
        $this->assertStringContainsString('multicolumn', $grid->extraClass());
    }

    public function testSetGridTemplateEmitsGridTemplateAndScopedClass(): void
    {
        $grid = GridLayoutField::create('Split', [TextField::create('Main'), TextField::create('Aside')])
            ->setGridTemplate('1.7fr 1fr');

        $this->assertSame('1.7fr 1fr', $grid->getGridTemplate());
        $this->assertStringContainsString('ss-grid-tpl-', $grid->extraClass());

        $style = $this->findStyleChild($grid);
        $this->assertInstanceOf(LiteralField::class, $style, 'setGridTemplate injects a hidden <style> carrier.');

        $content = (string) $style->getContent();
        $this->assertStringContainsString('<style>', $content);
        $this->assertStringContainsString('grid-template-columns:1.7fr 1fr', $content);
        // The rule is self-contained: it also forces the holder into grid mode (not relying on the base rule).
        $this->assertStringContainsString('display:grid', $content);
        $this->assertStringContainsString('> .form__field-holder', $content);
        // The rule is scoped by the per-instance class so a nested grid keeps the 12-col model.
        $this->assertStringContainsString('.ss-grid.ss-grid-tpl-', $content);
        // The carrier is marked so the CSS can hide it from the grid flow.
        $this->assertStringContainsString('ss-grid-style', $style->extraClass());
    }

    public function testSetGridTemplateIsResponsiveViaMediaQuery(): void
    {
        // Default breakpoint 'lg': stacks to one column, and the template applies only at/above 992px
        // viewport — so tablet + mobile stack; the ratio is a desktop layout.
        $grid = GridLayoutField::create('Split', [TextField::create('Main'), TextField::create('Aside')])
            ->setGridTemplate('1.7fr 1fr');
        $content = (string) $this->findStyleChild($grid)->getContent();
        // Mobile-first single-column default + the lg (992px) media query.
        $this->assertStringContainsString('grid-template-columns:1fr', $content);
        $this->assertStringContainsString('@media (min-width:992px)', $content);

        // A named breakpoint maps to its px.
        $md = GridLayoutField::create('SplitMd', [TextField::create('A'), TextField::create('B')])
            ->setGridTemplate('1fr 1fr', 'md');
        $mdStyle = (string) $this->findStyleChild($md)->getContent();
        $this->assertStringContainsString('@media (min-width:768px)', $mdStyle);

        // 'xs' opts out of collapsing — always-on, no media query.
        $xs = GridLayoutField::create('SplitXs', [TextField::create('A'), TextField::create('B')])
            ->setGridTemplate('1fr 1fr', 'xs');
        $xsStyle = (string) $this->findStyleChild($xs)->getContent();
        $this->assertStringNotContainsString('@media', $xsStyle);
    }

    public function testSetGridTemplateNullClearsIt(): void
    {
        $grid = GridLayoutField::create('Split', [TextField::create('Main')])
            ->setGridTemplate('1.7fr 1fr');
        $this->assertNotNull($grid->getGridTemplate());

        $grid->setGridTemplate(null);
        $this->assertNull($grid->getGridTemplate());
        $this->assertStringNotContainsString('ss-grid-tpl-', $grid->extraClass());
        $this->assertNull($this->findStyleChild($grid), 'The injected <style> carrier is removed.');
    }

    public function testSetGridTemplateSanitisesInjection(): void
    {
        // A hostile value must not be able to break out of the <style> rule. Use the 'xs' breakpoint so the
        // rule is emitted always-on (no @container wrapper), isolating the brace count to sanitisation.
        $grid = GridLayoutField::create('Evil', [TextField::create('X')])
            ->setGridTemplate('1fr}</style><script>alert(1)</script>', 'xs');

        $content = (string) $this->findStyleChild($grid)->getContent();
        $this->assertStringNotContainsStringIgnoringCase('<script', $content);
        // Exactly one rule block survives — the hostile extra braces were stripped.
        $this->assertSame(1, substr_count($content, '{'));
        $this->assertSame(1, substr_count($content, '}'));
    }

    public function testChildrenRemainSaveable(): void
    {
        // A grid's data fields must still collate for saving (CompositeField behaviour, preserved).
        $grid = GridLayoutField::create('Row', [TextField::create('A'), TextField::create('B')]);
        $list = [];
        $grid->collateDataFields($list);
        $this->assertArrayHasKey('A', $list);
        $this->assertArrayHasKey('B', $list);
    }

    private function findStyleChild(GridLayoutField $grid): ?LiteralField
    {
        foreach ($grid->getChildren() as $child) {
            if ($child instanceof LiteralField && str_contains((string) $child->getContent(), '<style>')) {
                return $child;
            }
        }
        return null;
    }
}
