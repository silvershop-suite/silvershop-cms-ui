<?php

declare(strict_types=1);

namespace SilverShop\CmsUi\Tests;

use SilverShop\CmsUi\Forms\PanelField;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;

class PanelFieldTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testCardShapeHeadAndBody(): void
    {
        $panel = PanelField::create('Summary', 'Order summary', [TextField::create('Ref')]);

        // The card hook on the outer composite.
        $this->assertStringContainsString('ss-panel', $panel->extraClass());

        // Head is a LiteralField; body is a nested composite classed ss-panel__body.
        $this->assertInstanceOf(LiteralField::class, $panel->getHead());
        $this->assertInstanceOf(CompositeField::class, $panel->getBody());
        $this->assertStringContainsString('ss-panel__body', $panel->getBody()->extraClass());

        // The real field went into the body, not alongside head/body.
        $this->assertCount(2, $panel->getChildren(), 'Direct children are exactly [head, body].');
        $this->assertNotNull($panel->getBody()->fieldByName('Ref'));
    }

    public function testHeadRendersHeadingWithTitleAndId(): void
    {
        $panel = PanelField::create('Summary', 'Order summary', []);
        $head = (string) $panel->getHead()->getContent();

        $this->assertStringContainsString('class="ss-panel__head"', $head);
        $this->assertStringContainsString('<h2', $head);
        $this->assertStringContainsString('Order summary', $head);
        $this->assertStringContainsString('id="Summary_title"', $head);
    }

    public function testHeadingLevel(): void
    {
        $panel = PanelField::create('S', 'T', [])->setHeadingLevel(3);
        $head = (string) $panel->getHead()->getContent();
        $this->assertStringContainsString('<h3', $head);
        $this->assertStringNotContainsString('<h2', $head);
    }

    public function testTitleIsEscaped(): void
    {
        $panel = PanelField::create('S', '<b>x</b>', []);
        $head = (string) $panel->getHead()->getContent();
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $head);
        $this->assertStringNotContainsString('<b>x</b>', $head);
    }

    public function testSetCountRendersMutedCount(): void
    {
        $panel = PanelField::create('S', 'Items', [])->setCount('12 items');
        $head = (string) $panel->getHead()->getContent();
        $this->assertStringContainsString('ss-panel__count', $head);
        $this->assertStringContainsString('12 items', $head);
    }

    public function testSetActionsRendersActionSlot(): void
    {
        $panel = PanelField::create('S', 'Items', [])
            ->setActions('<button class="btn">Edit</button>');
        $head = (string) $panel->getHead()->getContent();
        $this->assertStringContainsString('ss-panel__actions', $head);
        $this->assertStringContainsString('<button class="btn">Edit</button>', $head);
    }

    public function testSetActionsAcceptsFormField(): void
    {
        $panel = PanelField::create('S', 'Items', [])
            ->setActions(LiteralField::create('x', '<span class="act">go</span>'));
        $head = (string) $panel->getHead()->getContent();
        $this->assertStringContainsString('ss-panel__actions', $head);
        $this->assertStringContainsString('<span class="act">go</span>', $head);
    }

    public function testSetFlushTogglesBodyModifier(): void
    {
        $panel = PanelField::create('S', 'T', []);
        $this->assertFalse($panel->getFlush());
        $this->assertStringNotContainsString('ss-panel__body--flush', $panel->getBody()->extraClass());

        $panel->setFlush();
        $this->assertTrue($panel->getFlush());
        $this->assertStringContainsString('ss-panel__body--flush', $panel->getBody()->extraClass());

        $panel->setFlush(false);
        $this->assertFalse($panel->getFlush());
        $this->assertStringNotContainsString('ss-panel__body--flush', $panel->getBody()->extraClass());
    }

    public function testEmptyHeadRendersNothing(): void
    {
        $panel = PanelField::create('S', null, [TextField::create('A')]);
        $this->assertSame('', (string) $panel->getHead()->getContent());
    }

    public function testHeadlessWithActionsFallsBackToAccessibleTitle(): void
    {
        // Actions but no title: a visually-hidden translatable heading supplies an accessible name.
        $panel = PanelField::create('S', null, [])->setActions('<button>Go</button>');
        $head = (string) $panel->getHead()->getContent();
        $this->assertStringContainsString('ss-panel__title--sr', $head);
        $this->assertStringContainsString('Panel', $head);
    }

    public function testPushDelegatesToBody(): void
    {
        $panel = PanelField::create('S', 'T', []);
        $panel->push(TextField::create('Late'));

        // Still exactly [head, body] at the top level; the field landed inside the body.
        $this->assertCount(2, $panel->getChildren());
        $this->assertNotNull($panel->getBody()->fieldByName('Late'));
        $this->assertNotNull($panel->fieldByName('Late'), 'fieldByName looks through the body.');
    }

    public function testBodyFieldsCollateForSaving(): void
    {
        $panel = PanelField::create('S', 'T', [TextField::create('A'), TextField::create('B')]);
        $list = [];
        $panel->collateDataFields($list);
        $this->assertArrayHasKey('A', $list);
        $this->assertArrayHasKey('B', $list);
    }
}
