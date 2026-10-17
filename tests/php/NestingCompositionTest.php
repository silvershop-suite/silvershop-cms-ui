<?php

declare(strict_types=1);

namespace SilverShop\CmsUi\Tests;

use SilverShop\CmsUi\Forms\GridLayoutField;
use SilverShop\CmsUi\Forms\PanelField;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;

/**
 * The canonical nesting cases the layout primitives must compose into — the "reset + re-establish per
 * level" principle, verified structurally (class hooks at each level) and on the data path (leaf fields
 * collate through arbitrary depth, so saving works). The actual rendered geometry is verified live.
 */
class NestingCompositionTest extends SapphireTest
{
    protected $usesDatabase = false;

    /**
     * Build: grid (full width, 1.7fr/1fr) -> Main panel -> { 2-col grid, bare field, sub-grid -> 2 panels }.
     */
    private function buildTree(): GridLayoutField
    {
        $subGrid = GridLayoutField::create('SubGrid', [
            PanelField::create('Billing', 'Billing', [TextField::create('BillCity', 'City')]),
            PanelField::create('Shipping', 'Shipping', [TextField::create('ShipCity', 'City')]),
        ])->setColumns(['Billing' => 6, 'Shipping' => 6]);

        $main = PanelField::create('Main', 'Order summary', [
            GridLayoutField::create('InnerGrid', [
                TextField::create('FirstName', 'First name'),
                TextField::create('LastName', 'Last name'),
            ])->setColumns(['FirstName' => 6, 'LastName' => 6]),
            TextareaField::create('Notes', 'Notes'),
            $subGrid,
        ]);

        return GridLayoutField::create('Outer', [$main])
            ->enableFullWidth()
            ->setGridTemplate('1.7fr 1fr');
    }

    public function testClassHooksAtEveryLevel(): void
    {
        $outer = $this->buildTree();
        $this->assertStringContainsString('ss-grid', $outer->extraClass());
        $this->assertStringContainsString('ss-grid-full', $outer->extraClass());
        $this->assertStringContainsString('ss-grid-tpl-', $outer->extraClass());

        $main = $outer->fieldByName('Main');
        $this->assertInstanceOf(PanelField::class, $main);
        $this->assertStringContainsString('ss-panel', $main->extraClass());
        $this->assertStringContainsString('ss-panel__body', $main->getBody()->extraClass());
    }

    public function testGridInsidePanel(): void
    {
        // Case 1: grid -> panel -> grid (two columns inside a panel body).
        $outer = $this->buildTree();
        $innerGrid = $outer->fieldByName('Main')->getBody()->fieldByName('InnerGrid');
        $this->assertInstanceOf(GridLayoutField::class, $innerGrid);
        $this->assertStringContainsString('ss-grid', $innerGrid->extraClass());
        $this->assertStringContainsString('col-md-6', $innerGrid->fieldByName('FirstName')->extraClass());
        $this->assertStringContainsString('col-md-6', $innerGrid->fieldByName('LastName')->extraClass());
    }

    public function testBareFieldInsidePanel(): void
    {
        // Case 2: grid -> panel -> bare field (lives directly in the body, saveable).
        $outer = $this->buildTree();
        $notes = $outer->fieldByName('Main')->getBody()->fieldByName('Notes');
        $this->assertInstanceOf(TextareaField::class, $notes);
    }

    public function testPanelInsideGridInsidePanel(): void
    {
        // Case 3: grid -> panel -> grid -> panel (deepest). The sub-panels are grid cells with spans.
        $outer = $this->buildTree();
        $subGrid = $outer->fieldByName('Main')->getBody()->fieldByName('SubGrid');
        $this->assertInstanceOf(GridLayoutField::class, $subGrid);

        $billing = $subGrid->fieldByName('Billing');
        $this->assertInstanceOf(PanelField::class, $billing);
        $this->assertStringContainsString('ss-panel', $billing->extraClass());
        $this->assertStringContainsString('col-md-6', $billing->extraClass());
        $this->assertInstanceOf(TextField::class, $billing->getBody()->fieldByName('BillCity'));
    }

    public function testLeafFieldsCollateThroughArbitraryDepth(): void
    {
        // Saving must work: every leaf data field, however deep, is collated by the top-level composite.
        $outer = $this->buildTree();
        $list = [];
        $outer->collateDataFields($list);

        foreach (['FirstName', 'LastName', 'Notes', 'BillCity', 'ShipCity'] as $name) {
            $this->assertArrayHasKey($name, $list, "Leaf field {$name} collates for saving.");
        }
    }
}
