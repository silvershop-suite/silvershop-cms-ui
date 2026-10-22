<?php

declare(strict_types=1);

namespace SilverShop\CmsUi\Tests;

use ReflectionMethod;
use SilverShop\CmsUi\Forms\GridFieldFilterForm;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\TextField;
use SilverStripe\Model\List\ArrayList;

/**
 * Unit coverage for the always-visible filter form. The filter/sort/paginate behaviour itself is the
 * native GridFieldFilterHeader engine (inherited, not re-implemented), so these tests lock down what the
 * component adds: the public layout API and — the load-bearing detail — the `filter[<GridName>][<field>]`
 * field-name shape the inherited handleAction() reads. End-to-end filtering is verified live in the admin.
 */
class GridFieldFilterFormTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testExtendsNativeFilterHeader(): void
    {
        // Inheriting the native header IS the design: the filter engine (getActions/handleAction/
        // getManipulatedData/state) comes for free; only the presentation (getHTMLFragments) is overridden.
        $this->assertInstanceOf(GridFieldFilterHeader::class, GridFieldFilterForm::create());
    }

    public function testFluentApiSettersAndGetters(): void
    {
        $fields = FieldList::create(TextField::create('Reference'));
        $form = GridFieldFilterForm::create()
            ->setColumns(4)
            ->setColumnSpans(['Email' => 6, 'Status' => 20])
            ->setRestrictFields(['Reference', 'Name', 'Email'])
            ->setTitle('Filter')
            ->setFields($fields);

        $this->assertSame(4, $form->getColumns());
        // Spans clamp into 1..12 (20 -> 12), keys normalise to strings.
        $this->assertSame(['Email' => 6, 'Status' => 12], $form->getColumnSpans());
        $this->assertSame(['Reference', 'Name', 'Email'], $form->getRestrictFields());
        $this->assertSame('Filter', $form->getTitle());
        $this->assertSame($fields, $form->getFields());
    }

    public function testColumnDefaultsAndFloor(): void
    {
        $this->assertNull(GridFieldFilterForm::create()->getColumns(), 'Columns default to auto (null).');
        $this->assertSame(1, GridFieldFilterForm::create()->setColumns(0)->getColumns(), 'Columns floor at 1.');
        $this->assertNull(GridFieldFilterForm::create()->setColumns(4)->setColumns(null)->getColumns());
    }

    public function testFilterFieldNameShape(): void
    {
        // The crux: each field is renamed to filter[<GridName>][<field>] so the INHERITED handleAction()
        // (which reads $data['filter'][$gridField->getName()][$fieldName]) and getManipulatedData() pick
        // the submitted values up unchanged — no custom submit JS needed.
        $method = new ReflectionMethod(GridFieldFilterForm::class, 'filterFieldName');
        $method->setAccessible(true);
        $this->assertSame(
            'filter[MyGrid][Email]',
            $method->invoke(GridFieldFilterForm::create(), 'MyGrid', 'Email')
        );
    }

    public function testRestrictFieldsNarrowsTheFieldList(): void
    {
        $grid = GridField::create('TestGrid', null, ArrayList::create());
        $form = GridFieldFilterForm::create()
            ->setFields(FieldList::create(
                TextField::create('Reference'),
                TextField::create('Name'),
                TextField::create('Email'),
                TextField::create('Status')
            ))
            ->setRestrictFields(['Reference', 'Email']);

        $build = new ReflectionMethod(GridFieldFilterForm::class, 'buildFilterFields');
        $build->setAccessible(true);
        /** @var FieldList $result */
        $result = $build->invoke($form, $grid);

        $names = array_map(static fn ($field) => $field->getName(), $result->toArray());
        $this->assertSame(['Reference', 'Email'], $names, 'Only whitelisted fields survive.');
    }
}
