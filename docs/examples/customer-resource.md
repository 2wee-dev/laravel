# Example: Customer Resource (CRUD)

A complete CRUD resource with lookups and search.

```php
<?php

namespace App\TwoWee\Resources;

use TwoWee\Laravel\Columns\DecimalColumn;
use TwoWee\Laravel\Columns\TextColumn;
use TwoWee\Laravel\Enums\Color;
use TwoWee\Laravel\Fields\Boolean;
use TwoWee\Laravel\Fields\Decimal;
use TwoWee\Laravel\Fields\Email;
use TwoWee\Laravel\Fields\Phone;
use TwoWee\Laravel\Fields\Text;
use TwoWee\Laravel\Fields\Url;
use TwoWee\Laravel\Lookup\LookupDefinition;
use TwoWee\Laravel\Resource;
use TwoWee\Laravel\Section;

class CustomerResource extends Resource
{
    protected static string $model = \App\Models\Customer::class;
    protected static string $label = 'Customer';

    public static function form(): array
    {
        return [
            Section::make('General')
                ->column(0)->rowGroup(0)
                ->fields([
                    Text::make('no')->label('No.')->width(20)->required()
                        ->uppercase()
                        ->lookup('customer_no', 'name', validate: true),
                    Text::make('name')->label('Name')->width(30)->required(),
                    Text::make('address')->label('Address')->width(30),
                    Text::make('post_code')->label('Post Code')->width(10)
                        ->uppercase()
                        ->lookup('post_code', null, validate: true),
                    Text::make('city')->label('City')->width(20),
                ]),

            Section::make('Communication')
                ->column(1)->rowGroup(0)
                ->fields([
                    Phone::make('phone')->label('Phone No.')->width(20),
                    Email::make('email')->label('E-Mail')->width(30),
                    Url::make('homepage')->label('Home Page')->width(30),
                ]),

            Section::make('Invoicing')
                ->column(0)->rowGroup(1)
                ->fields([
                    Decimal::make('credit_limit')->label('Credit Limit (LCY)')
                        ->width(15)->decimals(2)->min(0),
                    Decimal::make('balance')->label('Balance (LCY)')
                        ->decimals(2)->disabled()->drillDown('customer_ledger')
                        ->color(Color::Yellow)->bold(),
                    Boolean::make('blocked')->label('Blocked'),
                ]),
        ];
    }

    public static function table(): array
    {
        return [
            TextColumn::make('no')->label('No.')->width(10),
            TextColumn::make('name')->label('Name')->width('fill'),
            TextColumn::make('city')->label('City')->width(15),
            TextColumn::make('phone')->label('Phone No.')->width(15),
            DecimalColumn::make('balance')->label('Balance')->decimals(2)->width(15)->align('right'),
        ];
    }

    public static function searchColumns(): ?array
    {
        return ['no', 'name', 'city', 'phone'];
    }

    public static function lookups(): array
    {
        return [
            'post_code' => LookupDefinition::make(\App\Models\PostCode::class)
                ->columns([
                    TextColumn::make('code')->label('Code')->width(10),
                    TextColumn::make('city')->label('City')->width('fill'),
                ])
                ->valueColumn('code')
                ->autofill(['city']),
        ];
    }
}
```
