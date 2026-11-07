<?php

declare(strict_types=1);

namespace SilverShop\CmsUi\Tests;

use SilverShop\CmsUi\Forms\PanelBodyField;
use SilverShop\CmsUi\Forms\PanelField;
use SilverShop\CmsUi\Forms\PanelHeaderField;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;

class PanelFieldTest extends SapphireTest
{
    protected $usesDatabase = false;

    private function headingHtml(PanelField $panel): string
    {
        return (string) $panel->getHeader()->getHeadingField()->getContent();
    }

    public function testCardShapeHeaderAndBody(): void
    {
        $panel = PanelField::create('Summary', 'Order summary', [TextField::create('Ref')]);

        $this->assertStringContainsString('ss-panel', $panel->extraClass());

        // The two parts are their own primitives.
        $this->assertInstanceOf(PanelHeaderField::class, $panel->getHeader());
        $this->assertInstanceOf(PanelBodyField::class, $panel->getBody());
        $this->assertSame($panel->getHeader(), $panel->getHead(), 'getHead() is a back-compat alias.');
        $this->assertStringContainsString('ss-panel__head', $panel->getHeader()->extraClass());
        $this->assertStringContainsString('ss-panel__body', $panel->getBody()->extraClass());

        // Top level is exactly [header, body]; the real field went into the body.
        $this->assertCount(2, $panel->getChildren(), 'Direct children are exactly [header, body].');
        $this->assertNotNull($panel->getBody()->fieldByName('Ref'));
    }

    public function testHeadingRendersTitleAndId(): void
    {
        $head = $this->headingHtml(PanelField::create('Summary', 'Order summary', []));

        $this->assertStringContainsString('class="ss-panel__heading"', $head);
        $this->assertStringContainsString('<h2', $head);
        $this->assertStringContainsString('Order summary', $head);
        $this->assertStringContainsString('_title"', $head);
    }

    public function testHeadingLevel(): void
    {
        $head = $this->headingHtml(PanelField::create('S', 'T', [])->setHeadingLevel(3));
        $this->assertStringContainsString('<h3', $head);
        $this->assertStringNotContainsString('<h2', $head);
    }

    public function testTitleIsEscaped(): void
    {
        $head = $this->headingHtml(PanelField::create('S', '<b>x</b>', []));
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $head);
        $this->assertStringNotContainsString('<b>x</b>', $head);
    }

    public function testSetCountRendersMutedCount(): void
    {
        $head = $this->headingHtml(PanelField::create('S', 'Items', [])->setCount('12 items'));
        $this->assertStringContainsString('ss-panel__count', $head);
        $this->assertStringContainsString('12 items', $head);
    }

    public function testSetIconRendersGlyph(): void
    {
        $head = $this->headingHtml(PanelField::create('S', 'Shipping', [])->setIcon('truck'));
        $this->assertStringContainsString('ss-icon-truck', $head);
        $this->assertStringContainsString('ss-panel__icon', $head);
    }

    public function testSetActionsRendersStaticCarrierNotHeading(): void
    {
        $panel = PanelField::create('S', 'Items', [])->setActions('<button class="btn">Edit</button>');

        // Static actions go into the header's actions container, not baked into the heading literal.
        $this->assertStringNotContainsString('<button', $this->headingHtml($panel));
        $container = $panel->getHeader()->getActionsContainer();
        $this->assertNotNull($container, 'An actions container is created for static actions.');
        $static = $container->fieldByName('SHeadStaticActions');
        $this->assertInstanceOf(LiteralField::class, $static);
        $this->assertStringContainsString('<button class="btn">Edit</button>', (string) $static->getContent());
    }

    public function testAddActionAddsLiveHeaderChildThatCollates(): void
    {
        $action = TextField::create('EditAct');
        $panel = PanelField::create('S', 'Items', [TextField::create('Ref')])->addAction($action);

        // Panel top level stays [header, body]; the action lives in the header's actions container, not the body.
        $this->assertCount(2, $panel->getChildren());
        $this->assertNull($panel->getBody()->fieldByName('EditAct'), 'The action is not in the body.');
        $this->assertNotNull(
            $panel->getHeader()->getActionsContainer()->fieldByName('EditAct'),
            'The action is a header-container child.'
        );
        $this->assertSame($action, $panel->getActionsField());
        $this->assertCount(1, $panel->getActions());

        // Hooks the CSS needs.
        $this->assertStringContainsString('ss-panel__action-field', $action->extraClass());
        $this->assertStringContainsString('ss-panel__head--actions', $panel->getHeader()->extraClass());

        // It stays a real form field: it collates for the form, so its own routes (detail form) resolve.
        $list = [];
        $panel->collateDataFields($list);
        $this->assertArrayHasKey('EditAct', $list);
    }

    public function testTwoActionsBothLive(): void
    {
        $panel = PanelField::create('S', 'Items', [])
            ->addAction(TextField::create('A'))
            ->addAction(TextField::create('B'));

        $this->assertCount(2, $panel->getActions());
        $container = $panel->getHeader()->getActionsContainer();
        $this->assertNotNull($container->fieldByName('A'));
        $this->assertNotNull($container->fieldByName('B'));
    }

    public function testSetActionsFieldReplacesAndClears(): void
    {
        $panel = PanelField::create('S', 'Items', [])->setActionsField(TextField::create('First'));
        $this->assertSame('First', $panel->getActionsField()->getName());

        $panel->setActionsField(TextField::create('Second'));
        $this->assertCount(1, $panel->getActions(), 'The previous live action is replaced, not appended.');
        $this->assertSame('Second', $panel->getActionsField()->getName());

        $panel->setActionsField(null);
        $this->assertNull($panel->getActionsField());
        $this->assertCount(0, $panel->getActions());
        $this->assertStringNotContainsString('ss-panel__head--actions', $panel->getHeader()->extraClass());
    }

    public function testHeadlessHeadCollapses(): void
    {
        // No title / count / icon / actions → empty head, collapsed by the modifier; heading renders nothing.
        $panel = PanelField::create('S', null, [TextField::create('A')]);
        $this->assertSame('', $this->headingHtml($panel));
        $this->assertStringContainsString('ss-panel__head--empty', $panel->getHeader()->extraClass());
    }

    public function testHeadlessWithActionsFallsBackToAccessibleTitle(): void
    {
        // Actions but no title: a visually-hidden heading supplies an accessible name, and the bar shows.
        $panel = PanelField::create('S', null, [])->addAction(TextField::create('Go'));
        $head = $this->headingHtml($panel);
        $this->assertStringContainsString('ss-panel__title--sr', $head);
        $this->assertStringContainsString('Panel', $head);
        $this->assertStringNotContainsString('ss-panel__head--empty', $panel->getHeader()->extraClass());
    }

    public function testSetFlushDelegatesToBody(): void
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

    public function testSetBodyMaxHeightInjectsScopedStyle(): void
    {
        $panel = PanelField::create('S', 'T', [])->setBodyMaxHeight('18em');
        $this->assertStringContainsString('ss-panel__body--scroll', $panel->getBody()->extraClass());

        // A hidden <style> carrier lives in the body.
        $style = null;
        foreach ($panel->getBody()->getChildren() as $child) {
            if (strpos($child->extraClass(), 'ss-panel__style') !== false) {
                $style = $child;
            }
        }
        $this->assertNotNull($style, 'A scoped <style> carrier is injected into the body.');
        $this->assertStringContainsString('max-height:18em', (string) $style->getContent());

        // Clearing removes it.
        $panel->setBodyMaxHeight(null);
        $this->assertStringNotContainsString('ss-panel__body--scroll', $panel->getBody()->extraClass());
    }

    public function testPushDelegatesToBody(): void
    {
        $panel = PanelField::create('S', 'T', []);
        $panel->push(TextField::create('Late'));

        $this->assertCount(2, $panel->getChildren(), 'Still [header, body] at the top level.');
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
