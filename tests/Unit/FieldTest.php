<?php

namespace TwoWee\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TwoWee\Laravel\Enums\Color;
use TwoWee\Laravel\Fields\Boolean;
use TwoWee\Laravel\Fields\Date;
use TwoWee\Laravel\Fields\Decimal;
use TwoWee\Laravel\Fields\Email;
use TwoWee\Laravel\Fields\Integer;
use TwoWee\Laravel\Fields\Option;
use TwoWee\Laravel\Fields\Phone;
use TwoWee\Laravel\Fields\Separator;
use TwoWee\Laravel\Fields\Text;
use TwoWee\Laravel\Fields\Url;

class FieldTest extends TestCase
{
    public function test_text_field_to_array(): void
    {
        $result = Text::make('name')
            ->label('Name')
            ->width(30)
            ->required()
            ->maxLength(100)
            ->toArray();

        $this->assertSame('name', $result['id']);
        $this->assertSame('Name', $result['label']);
        $this->assertSame('Text', $result['type']);
        $this->assertSame('', $result['value']);
        $this->assertTrue($result['editable']);
        $this->assertSame(30, $result['width']);
        $this->assertSame(['required' => true, 'max_length' => 100], $result['validation']);
        // triggers removed from protocol
        $this->assertArrayNotHasKey('triggers', $result);
    }

    public function test_text_field_with_value(): void
    {
        $result = Text::make('name')->label('Name')->toArray('John');

        $this->assertSame('John', $result['value']);
    }

    public function test_text_field_with_input_mask_and_lookup(): void
    {
        $result = Text::make('no')
            ->label('No.')
            ->uppercase()
            ->lookup('customers')
            ->toArray();

        $this->assertSame('Text', $result['type']);
        $this->assertSame('uppercase', $result['validation']['input_mask']);
        $this->assertSame(['endpoint' => '/lookup/customers'], $result['lookup']);
    }

    public function test_lookup_with_display_field(): void
    {
        $result = Text::make('no')
            ->lookup('customers', 'name')
            ->toArray();

        $this->assertSame([
            'endpoint' => '/lookup/customers',
            'display_field' => 'name',
        ], $result['lookup']);
    }

    public function test_lookup_with_validate(): void
    {
        $result = Text::make('no')
            ->lookup('customers', 'name', validate: true)
            ->toArray();

        $this->assertSame('/lookup/customers', $result['lookup']['endpoint']);
        $this->assertSame('name', $result['lookup']['display_field']);
        $this->assertStringContainsString('/validate/customers', $result['lookup']['validate']);
    }

    public function test_lookup_with_display_mode(): void
    {
        $result = Text::make('no')
            ->lookup('customers', 'name', display: 'modal')
            ->toArray();

        $this->assertSame([
            'endpoint' => '/lookup/customers',
            'display_field' => 'name',
            'display' => 'modal',
        ], $result['lookup']);
    }

    public function test_decimal_field(): void
    {
        $result = Decimal::make('amount')
            ->label('Amount')
            ->decimals(2)
            ->min(0)
            ->max(999999.99)
            ->toArray(1234.5);

        $this->assertSame('Decimal', $result['type']);
        // Default locale uses , as decimal and . as thousand
        $this->assertSame('1234.50', $result['value']);
        $this->assertSame(2, $result['validation']['decimals']);
        $this->assertSame(0.0, $result['validation']['min']);
        $this->assertSame(999999.99, $result['validation']['max']);
    }

    public function test_integer_field(): void
    {
        $result = Integer::make('qty')
            ->label('Quantity')
            ->min(1)
            ->max(10000)
            ->toArray(42);

        $this->assertSame('Integer', $result['type']);
        $this->assertSame('42', $result['value']);
        $this->assertSame(1, $result['validation']['min']);
    }

    public function test_date_field(): void
    {
        $result = Date::make('posting_date')
            ->label('Posting Date')
            ->format('DD-MM-YY')
            ->toArray();

        $this->assertSame('Date', $result['type']);
        $this->assertSame('DD-MM-YY', $result['validation']['format']);
    }

    public function test_boolean_field(): void
    {
        $result = Boolean::make('blocked')->label('Blocked')->toArray(true);

        $this->assertSame('Boolean', $result['type']);
        $this->assertSame('true', $result['value']);
    }

    public function test_boolean_parse_value(): void
    {
        $field = Boolean::make('blocked');

        $this->assertTrue($field->parseValue('true'));
        $this->assertTrue($field->parseValue('1'));
        $this->assertFalse($field->parseValue('false'));
        $this->assertFalse($field->parseValue('0'));
        $this->assertFalse($field->parseValue(''));
    }

    public function test_option_field_with_associative_array(): void
    {
        $result = Option::make('type')
            ->label('Type')
            ->options(['item' => 'Item', 'gl' => 'G/L Account'])
            ->toArray('item');

        $this->assertSame('Option', $result['type']);
        $this->assertSame('item', $result['value']);
        $this->assertSame([
            ['value' => 'item', 'label' => 'Item'],
            ['value' => 'gl', 'label' => 'G/L Account'],
        ], $result['options']);
    }

    public function test_option_field_with_simple_array(): void
    {
        $result = Option::make('status')
            ->options(['Active', 'Inactive'])
            ->toArray();

        $this->assertSame([
            ['value' => 'Active', 'label' => 'Active'],
            ['value' => 'Inactive', 'label' => 'Inactive'],
        ], $result['options']);
    }

    public function test_drill_down_on_any_field(): void
    {
        $result = Decimal::make('balance')
            ->label('Balance')
            ->disabled()
            ->drillDown('ledger_entries')
            ->color(Color::Yellow)->bold()
            ->toArray('45000.00');

        $this->assertSame('Decimal', $result['type']);
        $this->assertFalse($result['editable']);
        $this->assertSame(['endpoint' => 'ledger_entries'], $result['lookup']);
        $this->assertSame('yellow', $result['color']);
        $this->assertTrue($result['bold']);
    }

    public function test_drill_down_on_integer_field(): void
    {
        $result = Integer::make('order_count')
            ->disabled()
            ->drillDown('orders')
            ->toArray(42);

        $this->assertSame('Integer', $result['type']);
        $this->assertFalse($result['editable']);
        $this->assertSame(['endpoint' => 'orders'], $result['lookup']);
    }

    public function test_drill_down_with_id_placeholder(): void
    {
        $model = new \stdClass();
        $model->id = 10000;
        // Simulate getKey()
        $mockModel = new class {
            public function getKey() { return 10000; }
        };

        $result = Decimal::make('balance')
            ->disabled()
            ->drillDown('/drilldown/balance/{id}')
            ->toArray('45000.00', $mockModel);

        $this->assertSame('/drilldown/balance/10000', $result['lookup']['endpoint']);
    }

    public function test_drill_down_with_closure(): void
    {
        $mockModel = new class {
            public function getKey() { return 42; }
        };

        $result = Decimal::make('balance')
            ->disabled()
            ->drillDown(fn ($model) => '/drilldown/balance/' . $model->getKey())
            ->toArray('100.00', $mockModel);

        $this->assertSame('/drilldown/balance/42', $result['lookup']['endpoint']);
    }

    public function test_drill_down_without_model_keeps_placeholder(): void
    {
        $result = Decimal::make('balance')
            ->disabled()
            ->drillDown('/drilldown/balance/{id}')
            ->toArray('100.00');

        $this->assertSame('/drilldown/balance/{id}', $result['lookup']['endpoint']);
    }

    public function test_disabled_field(): void
    {
        $result = Text::make('code')->disabled()->toArray();

        $this->assertFalse($result['editable']);
    }

    public function test_hidden_field(): void
    {
        $field = Text::make('secret')->hidden();

        $this->assertTrue($field->isHidden());
    }

    public function test_default_value(): void
    {
        $result = Text::make('country')->default('IS')->toArray();

        $this->assertSame('IS', $result['value']);
    }

    public function test_placeholder(): void
    {
        $result = Text::make('search')->placeholder('Search...')->toArray();

        $this->assertSame('Search...', $result['placeholder']);
    }

    public function test_quick_entry_default_true(): void
    {
        $result = Text::make('name')->toArray();

        $this->assertArrayNotHasKey('quick_entry', $result);
    }

    public function test_quick_entry_false(): void
    {
        $result = Text::make('name')->quickEntry(false)->toArray();

        $this->assertFalse($result['quick_entry']);
    }

    public function test_focus(): void
    {
        $result = Text::make('customer_no')->focus()->toArray();

        $this->assertTrue($result['focus']);
    }

    public function test_no_focus_by_default(): void
    {
        $result = Text::make('name')->toArray();

        $this->assertArrayNotHasKey('focus', $result);
    }

    public function test_no_triggers_in_output(): void
    {
        $result = Text::make('name')->toArray();

        $this->assertArrayNotHasKey('triggers', $result);
    }

    public function test_email_field_type(): void
    {
        $this->assertSame('Email', Email::make('email')->toArray()['type']);
    }

    public function test_phone_field_type(): void
    {
        $this->assertSame('Phone', Phone::make('phone')->toArray()['type']);
    }

    public function test_url_field_type(): void
    {
        $this->assertSame('URL', Url::make('website')->toArray()['type']);
    }

    public function test_color_and_bold(): void
    {
        $result = Text::make('highlight')
            ->color(Color::Red)->bold()
            ->toArray();

        $this->assertSame('red', $result['color']);
        $this->assertTrue($result['bold']);
    }

    public function test_color_only(): void
    {
        $result = Text::make('highlight')
            ->color(Color::Green)
            ->toArray();

        $this->assertSame('green', $result['color']);
        $this->assertArrayNotHasKey('bold', $result);
    }

    public function test_no_color_by_default(): void
    {
        $result = Text::make('name')->toArray();

        $this->assertArrayNotHasKey('color', $result);
        $this->assertArrayNotHasKey('bold', $result);
    }

    public function test_min_length(): void
    {
        $result = Text::make('code')
            ->minLength(3)
            ->maxLength(10)
            ->toArray();

        $this->assertSame(3, $result['validation']['min_length']);
        $this->assertSame(10, $result['validation']['max_length']);
    }

    public function test_input_mask_on_base_field(): void
    {
        $result = Text::make('code')
            ->inputMask('uppercase')
            ->toArray();

        $this->assertSame('uppercase', $result['validation']['input_mask']);
    }

    public function test_uppercase_shorthand(): void
    {
        $result = Text::make('no')->label('No.')->uppercase()->toArray();

        $this->assertSame('Text', $result['type']);
        $this->assertSame('uppercase', $result['validation']['input_mask']);
    }

    public function test_uppercase_convenience(): void
    {
        $result = Text::make('country_code')->uppercase()->toArray();

        $this->assertSame('uppercase', $result['validation']['input_mask']);
    }

    public function test_lowercase_convenience(): void
    {
        $result = Text::make('slug')->lowercase()->toArray();

        $this->assertSame('lowercase', $result['validation']['input_mask']);
    }

    public function test_digits_only_convenience(): void
    {
        $result = Text::make('phone')->digitsOnly()->toArray();

        $this->assertSame('digits_only', $result['validation']['input_mask']);
    }

    public function test_pattern_on_base_field(): void
    {
        $result = Text::make('code')
            ->pattern('^[A-Z]{2}\d{4}$')
            ->toArray();

        $this->assertSame('^[A-Z]{2}\d{4}$', $result['validation']['pattern']);
    }

    public function test_pattern_also_adds_server_rule(): void
    {
        $field = Text::make('code')
            ->pattern('^[A-Z]+$');

        $rules = $field->getServerRules();
        $this->assertContains('regex:^[A-Z]+$', $rules);
    }

    public function test_backed_enum_value(): void
    {
        $enum = TestBackedEnum::Active;

        $result = Text::make('status')->toArray($enum);

        $this->assertSame('active', $result['value']);
    }

    public function test_blur_validate_emits_lookup_with_validate_url(): void
    {
        $result = Text::make('name')
            ->blurValidate()
            ->toArray();

        $this->assertSame('name', $result['lookup']['endpoint']);
        $this->assertStringContainsString('/validate/name', $result['lookup']['validate']);
    }

    public function test_blur_validate_does_not_override_existing_lookup(): void
    {
        $result = Text::make('customer_no')
            ->lookup('customer_no', 'customer_name', validate: true)
            ->blurValidate()
            ->toArray();

        // lookup() takes precedence — blurValidate doesn't add a second lookup
        $this->assertSame('/lookup/customer_no', $result['lookup']['endpoint']);
        $this->assertSame('customer_name', $result['lookup']['display_field']);
        $this->assertStringContainsString('/validate/customer_no', $result['lookup']['validate']);
    }

    public function test_blur_validate_without_lookup_has_blur_flag(): void
    {
        $field = Text::make('name')->blurValidate();

        $this->assertTrue($field->hasBlurValidate());
    }

    public function test_no_blur_validate_by_default(): void
    {
        $field = Text::make('name');

        $this->assertFalse($field->hasBlurValidate());
        $this->assertArrayNotHasKey('lookup', $field->toArray());
    }

    public function test_field_server_rules_from_required_and_lengths(): void
    {
        $field = Text::make('name')
            ->required()
            ->minLength(3)
            ->maxLength(100);

        $rules = $field->getServerRules();

        $this->assertContains('required', $rules);
        $this->assertContains('min:3', $rules);
        $this->assertContains('max:100', $rules);
    }

    public function test_field_server_rules_with_explicit_rules(): void
    {
        $field = Text::make('code')
            ->rules(['alpha_num', 'uppercase']);

        $rules = $field->getServerRules();

        $this->assertContains('alpha_num', $rules);
        $this->assertContains('uppercase', $rules);
    }

    public function test_field_email_rule(): void
    {
        $field = Email::make('email')
            ->email()
            ->nullable();

        $rules = $field->getServerRules();

        $this->assertContains('nullable', $rules);
        $this->assertContains('email', $rules);
    }

    public function test_field_unique_rule_without_model(): void
    {
        $field = Text::make('email')
            ->unique('users', 'email');

        $rules = $field->getServerRules(null);

        // Should contain a Rule\Unique instance
        $hasUnique = false;
        foreach ($rules as $rule) {
            if ($rule instanceof \Illuminate\Validation\Rules\Unique) {
                $hasUnique = true;
                break;
            }
        }
        $this->assertTrue($hasUnique);
    }

    public function test_field_enum_rule(): void
    {
        $field = Option::make('status')
            ->enum(TestBackedEnum::class);

        $rules = $field->getServerRules();

        $hasEnum = false;
        foreach ($rules as $rule) {
            if ($rule instanceof \Illuminate\Validation\Rules\Enum) {
                $hasEnum = true;
                break;
            }
        }
        $this->assertTrue($hasEnum);
    }

    public function test_field_has_no_server_rules_by_default(): void
    {
        $field = Text::make('name');

        $this->assertFalse($field->hasServerRules());
        $this->assertEmpty($field->getServerRules());
    }

    public function test_field_has_server_rules_when_required(): void
    {
        $field = Text::make('name')->required();

        $this->assertTrue($field->hasServerRules());
    }

    public function test_numeric_rule(): void
    {
        $rules = Decimal::make('amount')->numeric()->getServerRules();

        $this->assertContains('numeric', $rules);
    }

    public function test_string_rule(): void
    {
        $rules = Text::make('name')->string()->getServerRules();

        $this->assertContains('string', $rules);
    }

    public function test_after_before_rules(): void
    {
        $rules = Date::make('end_date')->after('start_date')->getServerRules();

        $this->assertContains('after:start_date', $rules);
    }

    public function test_same_different_rules(): void
    {
        $rules = Text::make('confirm_email')->same('email')->getServerRules();

        $this->assertContains('same:email', $rules);
    }

    public function test_relationship_sets_lookup(): void
    {
        $field = Text::make('country_id')
            ->relationship('country', 'name');

        $this->assertTrue($field->hasRelationship());
        $this->assertSame('country', $field->getRelationshipName());
        $this->assertSame('name', $field->getRelationshipTitleAttribute());

        $result = $field->toArray();
        $this->assertSame('/lookup/country_id', $result['lookup']['endpoint']);
        $this->assertSame('name', $result['lookup']['display_field']);
        $this->assertStringContainsString('/validate/country_id', $result['lookup']['validate']);
    }

    public function test_context_single_field(): void
    {
        $result = Text::make('post_code')
            ->lookup('post_code', null, validate: true)
            ->filterFrom('country_code')
            ->toArray();

        $this->assertSame([['field' => 'country_code']], $result['lookup']['context']);
    }

    public function test_context_multiple_fields(): void
    {
        $result = Text::make('post_code')
            ->lookup('post_code')
            ->filterFrom('country_code', 'region_code')
            ->toArray();

        $this->assertSame([
            ['field' => 'country_code'],
            ['field' => 'region_code'],
        ], $result['lookup']['context']);
    }

    public function test_context_with_param_alias(): void
    {
        $result = Text::make('account_no')
            ->lookup('account_no')
            ->filterFrom(['field' => 'account_type', 'param' => 'type'])
            ->toArray();

        $this->assertSame([
            ['field' => 'account_type', 'param' => 'type'],
        ], $result['lookup']['context']);
    }

    public function test_no_context_by_default(): void
    {
        $result = Text::make('name')
            ->lookup('customers')
            ->toArray();

        $this->assertArrayNotHasKey('context', $result['lookup']);
    }

    public function test_modal_lookup(): void
    {
        $result = Text::make('currency_code')
            ->lookup('currency_code', null, validate: true, display: 'modal')
            ->toArray();

        $this->assertSame('modal', $result['lookup']['display']);
    }

    public function test_modal_convenience_method(): void
    {
        $result = Text::make('payment_terms')
            ->lookup('payment_terms')
            ->modal()
            ->toArray();

        $this->assertSame('modal', $result['lookup']['display']);
    }

    public function test_no_display_by_default(): void
    {
        $result = Text::make('customer_no')
            ->lookup('customer_no')
            ->toArray();

        $this->assertArrayNotHasKey('display', $result['lookup']);
    }

    public function test_inline_lookup_with_model_class(): void
    {
        // Can't test with a real model in unit tests, but we can verify the properties
        $field = Text::make('post_code');

        // Simulate what happens when lookup() receives a non-model string
        $field->lookup('post_code', 'name', validate: true);
        $this->assertFalse($field->hasInlineLookup());

        // Test autofill
        $field2 = Text::make('test')->autofill(['name' => 'city']);
        $this->assertSame(['name' => 'city'], $field2->getLookupAutofill());
    }

    public function test_filter_from(): void
    {
        $result = Text::make('post_code')
            ->lookup('post_code', null, validate: true)
            ->filterFrom('country_code')
            ->toArray();

        $this->assertSame([['field' => 'country_code']], $result['lookup']['context']);
    }

    public function test_filter_from_multiple(): void
    {
        $result = Text::make('post_code')
            ->lookup('post_code', null, validate: true)
            ->filterFrom('country_code', 'region_code')
            ->toArray();

        $this->assertSame([
            ['field' => 'country_code'],
            ['field' => 'region_code'],
        ], $result['lookup']['context']);
    }

    public function test_filter_from_with_param_alias(): void
    {
        $result = Text::make('account_no')
            ->lookup('account_no')
            ->filterFrom(['field' => 'account_type', 'param' => 'type'])
            ->toArray();

        $this->assertSame([
            ['field' => 'account_type', 'param' => 'type'],
        ], $result['lookup']['context']);
    }

    public function test_aggregate_disables_field(): void
    {
        $field = Decimal::make('balance')
            ->aggregate('ledgerEntries', 'sum', 'remaining_amount');

        $this->assertTrue($field->hasAggregate());
        $this->assertSame('ledgerEntries', $field->getAggregateTarget());
        $this->assertSame('sum', $field->getAggregateFunction());
        $this->assertSame('remaining_amount', $field->getAggregateColumn());

        $result = $field->toArray();
        $this->assertFalse($result['editable']);
    }

    public function test_aggregate_with_drilldown_target(): void
    {
        $field = Decimal::make('balance')
            ->aggregate('ledgerEntries', 'sum', 'remaining_amount')
            ->drillDown('ledgerEntries')
            ->color(Color::Yellow)->bold();

        $this->assertSame('ledgerEntries', $field->getDrillDownTarget());

        // Without model, drillDown falls through to static string
        $result = $field->toArray();
        $this->assertSame('ledgerEntries', $result['lookup']['endpoint']);
    }

    public function test_aggregate_count_no_column(): void
    {
        $field = Integer::make('order_count')
            ->aggregate('orders', 'count');

        $this->assertTrue($field->hasAggregate());
        $this->assertNull($field->getAggregateColumn());
    }

    public function test_disable_on_update(): void
    {
        // Without model (create) — field is editable
        $result = Text::make('customer_no')
            ->disableOnUpdate()
            ->toArray(null, null);

        $this->assertTrue($result['editable']);

        // With model (update) — field is disabled
        $model = new \stdClass();
        $result = Text::make('customer_no')
            ->disableOnUpdate()
            ->toArray('C001', $model);

        $this->assertFalse($result['editable']);
    }

    public function test_disable_on_create(): void
    {
        // Without model (create) — field is disabled
        $result = Text::make('status')
            ->disableOnCreate()
            ->toArray();

        $this->assertFalse($result['editable']);

        // With model (update) — field is editable
        $model = new \stdClass();
        $result = Text::make('status')
            ->disableOnCreate()
            ->toArray('active', $model);

        $this->assertTrue($result['editable']);
    }

    public function test_disabled_always_overrides_context(): void
    {
        $model = new \stdClass();
        $result = Text::make('code')
            ->disabled()
            ->toArray('X', $model);

        $this->assertFalse($result['editable']);
    }

    public function test_is_disabled_for(): void
    {
        $field = Text::make('no')->disableOnUpdate();

        $this->assertFalse($field->isDisabledFor(null)); // create
        $this->assertTrue($field->isDisabledFor(new \stdClass())); // update
    }

    public function test_resolve_using(): void
    {
        $result = Decimal::make('price')
            ->resolveUsing(fn ($value, $model) => $value !== null ? $value / 100 : null)
            ->toArray(4500);

        $this->assertSame('45', $result['value']);
    }

    public function test_resolve_using_with_null_value(): void
    {
        $result = Text::make('computed')
            ->disabled()
            ->resolveUsing(fn ($value, $model) => 'always this')
            ->toArray(null);

        $this->assertSame('always this', $result['value']);
    }

    public function test_fill_using(): void
    {
        $field = Decimal::make('price')
            ->fillUsing(fn ($value, $model) => (int) ($value * 100));

        $this->assertTrue($field->hasFillCallback());
        $this->assertSame(4500, $field->applyFill(45.00));
    }

    public function test_creation_rules(): void
    {
        $field = Text::make('code')
            ->creationRules(['unique:items,code']);

        // On create (null model) — includes creation rules
        $rules = $field->getServerRules(null);
        $this->assertContains('unique:items,code', $rules);

        // On update (model present) — excludes creation rules
        $model = new TestModel();
        $rules = $field->getServerRules($model);
        $this->assertNotContains('unique:items,code', $rules);
    }

    public function test_update_rules(): void
    {
        $field = Text::make('status')
            ->updateRules(['in:active,archived']);

        // On create (null model) — excludes update rules
        $rules = $field->getServerRules(null);
        $this->assertNotContains('in:active,archived', $rules);

        // On update (model present) — includes update rules
        $model = new TestModel();
        $rules = $field->getServerRules($model);
        $this->assertContains('in:active,archived', $rules);
    }

    public function test_creation_and_update_rules_combined(): void
    {
        $field = Text::make('email')
            ->rules(['email'])
            ->creationRules(['required'])
            ->updateRules(['sometimes']);

        $createRules = $field->getServerRules(null);
        $this->assertContains('email', $createRules);
        $this->assertContains('required', $createRules);
        $this->assertNotContains('sometimes', $createRules);

        $updateRules = $field->getServerRules(new TestModel());
        $this->assertContains('email', $updateRules);
        $this->assertContains('sometimes', $updateRules);
        $this->assertNotContains('required', $updateRules);
    }

    public function test_separator(): void
    {
        $result = Separator::make()->toArray();

        $this->assertSame('separator', $result['id']);
        $this->assertSame('Separator', $result['type']);
        $this->assertSame('', $result['label']);
        $this->assertSame('', $result['value']);
    }

    public function test_separator_ignores_model_value(): void
    {
        $result = Separator::make()->toArray('some_value');

        $this->assertSame('', $result['value']);
    }
}

enum TestBackedEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

class TestModel extends \Illuminate\Database\Eloquent\Model
{
    protected $guarded = [];
}
