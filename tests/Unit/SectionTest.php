<?php

namespace TwoWee\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TwoWee\Laravel\Fields\Text;
use TwoWee\Laravel\Section;

class SectionTest extends TestCase
{
    public function test_section_to_array(): void
    {
        $section = Section::make('General')
            ->column(0)
            ->rowGroup(0)
            ->fields([
                Text::make('no')->label('No.')->width(20)->required(),
                Text::make('name')->label('Name')->width(30),
            ]);

        $result = $section->toArray();

        $this->assertSame('general', $result['id']);
        $this->assertSame('General', $result['label']);
        $this->assertSame(0, $result['column']);
        $this->assertSame(0, $result['row_group']);
        $this->assertCount(2, $result['fields']);
        $this->assertSame('no', $result['fields'][0]['id']);
        $this->assertSame('name', $result['fields'][1]['id']);
    }

    public function test_section_excludes_hidden_fields(): void
    {
        $section = Section::make('Test')
            ->fields([
                Text::make('visible')->label('Visible'),
                Text::make('hidden')->label('Hidden')->hidden(),
            ]);

        $result = $section->toArray();

        $this->assertCount(1, $result['fields']);
        $this->assertSame('visible', $result['fields'][0]['id']);
    }

    public function test_section_with_model_values(): void
    {
        $model = new \stdClass();
        $model->no = '10000';
        $model->name = 'Test Customer';

        $section = Section::make('General')
            ->fields([
                Text::make('no')->label('No.'),
                Text::make('name')->label('Name'),
            ]);

        $result = $section->toArray($model);

        $this->assertSame('10000', $result['fields'][0]['value']);
        $this->assertSame('Test Customer', $result['fields'][1]['value']);
    }
}
