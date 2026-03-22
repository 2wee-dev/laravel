<?php

namespace TwoWee\Laravel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use TwoWee\Laravel\Columns\Column;
use TwoWee\Laravel\Columns\TextColumn;
use TwoWee\Laravel\Fields\Field;

abstract class Resource
{
    protected static string $model;

    protected static string $label = '';

    protected static ?string $slug = null;

    protected static bool $showInNavigation = true;

    protected static int $navigationSort = 0;

    protected static ?string $navigationGroup = null;

    protected static ?string $navigationLabel = null;

    protected static ?string $navigationPopup = null;

    protected static bool $navigationSeparatorBefore = false;

    protected static ?string $navigationParent = null;

    protected static ?string $recordKey = null;

    protected static ?string $screenId = null;

    protected static ?string $saveAction = null;

    protected static ?string $deleteAction = null;

    /** @return Section[] */
    abstract public static function form(): array;

    /** @return \TwoWee\Laravel\Columns\Column[] */
    abstract public static function table(): array;

    public static function getModel(): string
    {
        return static::$model;
    }

    public static function getLabel(): string
    {
        if (static::$label !== '') {
            return static::$label;
        }

        return Str::headline(class_basename(static::$model));
    }

    public static function getSlug(): string
    {
        if (static::$slug !== null) {
            return static::$slug;
        }

        return Str::plural(Str::snake(class_basename(static::$model)));
    }

    public static function shouldShowInNavigation(): bool
    {
        return static::$showInNavigation;
    }

    public static function title(Model $model): string
    {
        return static::getLabel() . ' - ' . static::getRecordKeyValue($model);
    }

    public static function getNavigationSort(): int
    {
        return static::$navigationSort;
    }

    public static function getNavigationGroup(): ?string
    {
        return static::$navigationGroup;
    }

    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel ?? static::getLabel();
    }

    public static function getNavigationPopup(): ?string
    {
        return static::$navigationPopup;
    }

    public static function hasNavigationSeparatorBefore(): bool
    {
        return static::$navigationSeparatorBefore;
    }

    /**
     * The column used to identify records in URLs.
     * Defaults to the model's primary key.
     */
    public static function getRecordKey(): string
    {
        if (static::$recordKey !== null) {
            return static::$recordKey;
        }

        return (new (static::$model)())->getKeyName();
    }

    /**
     * The stable machine identifier for this resource.
     * Defaults to the slug. Override for custom values.
     */
    public static function getScreenId(): string
    {
        return static::$screenId ?? static::getSlug();
    }

    public static function getSaveAction(): ?string
    {
        return static::$saveAction;
    }

    public static function getDeleteAction(): ?string
    {
        return static::$deleteAction;
    }

    /**
     * Find a record by the resource's record key.
     */
    public static function findRecord(string $id): ?\Illuminate\Database\Eloquent\Model
    {
        return static::$model::where(static::getRecordKey(), $id)->first();
    }

    /**
     * Get the record key value from a model instance.
     */
    public static function getRecordKeyValue(Model $model): string
    {
        return (string) $model->{static::getRecordKey()};
    }

    /**
     * Build a full URL path for this resource's card screen.
     */
    public static function cardUrl(string $recordId): string
    {
        $base = \TwoWee\Laravel\TwoWee::baseUrl();

        return $base . '/screen/' . static::getSlug() . '/card/' . $recordId;
    }

    /**
     * Build a full URL path for this resource's list screen.
     */
    public static function listUrl(): string
    {
        $base = \TwoWee\Laravel\TwoWee::baseUrl();

        return $base . '/screen/' . static::getSlug() . '/list';
    }

    /**
     * The parent URL for Escape fallback when there's no navigation history.
     * Defaults to /menu/main. Set $navigationParent for resources under sub-menus.
     * Card/HeaderLines auto-set to the list URL (not this method).
     */
    public static function parentUrl(): ?string
    {
        $base = \TwoWee\Laravel\TwoWee::baseUrl();

        if (static::$navigationParent !== null) {
            return $base . '/' . ltrim(static::$navigationParent, '/');
        }

        return $base . '/menu/main';
    }

    /**
     * Maximum rows to return in list view. Null = no limit.
     */
    public static function maxRows(): ?int
    {
        return null;
    }

    /**
     * Columns to search when ?query= is provided. Default: all Text columns.
     */
    public static function searchColumns(): ?array
    {
        return null;
    }

    /**
     * Additional Laravel validation rules for card/header fields.
     * Override to add rules that can't be expressed on fields (e.g. cross-field rules).
     * These are merged with rules collected from fields — field rules take precedence.
     */
    public static function rules(?Model $model = null): array
    {
        return [];
    }

    /**
     * Collect all validation rules from form fields + rules() override.
     * Field-level rules take precedence over rules() for the same key.
     */
    public static function collectRules(?Model $model = null): array
    {
        $fieldRules = [];

        foreach (static::form() as $section) {
            foreach ($section->getFields() as $field) {
                $rules = $field->getServerRules($model);
                if (! empty($rules)) {
                    $fieldRules[$field->getName()] = $rules;
                }
            }
        }

        // rules() provides defaults; field-level rules override
        return array_merge(static::rules($model), $fieldRules);
    }

    /**
     * Get validation rules for a single field by id.
     */
    public static function getFieldRules(string $fieldId, ?Model $model = null): array
    {
        foreach (static::form() as $section) {
            foreach ($section->getFields() as $field) {
                if ($field->getName() === $fieldId) {
                    $rules = $field->getServerRules($model);
                    if (! empty($rules)) {
                        return $rules;
                    }

                    // Fall back to rules() override
                    $allRules = static::rules($model);

                    return $allRules[$fieldId] ?? [];
                }
            }
        }

        return [];
    }

    /**
     * Laravel validation rules for each line row in HeaderLines/Grid layouts.
     * Applied per non-empty row. Keys are column names from lineColumns().
     */
    public static function lineRules(): array
    {
        return [];
    }

    /**
     * Called before saving header changes. Override to mutate or validate.
     * Return the changes array (modified or not).
     */
    public static function beforeSave(array $changes, ?Model $model = null): array
    {
        return $changes;
    }

    /**
     * Called after saving the model and lines. Override to run calculations,
     * update computed fields, sync related data, etc.
     * The model has been saved and lines have been synced at this point.
     */
    public static function afterSave(Model $model): void {}

    /**
     * Called after a new record is created and saved.
     * Use for sending notifications, firing events, etc.
     */
    public static function afterCreate(Model $model): void {}

    /**
     * Called after an existing record is updated and saved.
     */
    public static function afterUpdate(Model $model): void {}

    /**
     * Called after a record is deleted.
     */
    public static function afterDelete(Model $model): void {}

    /**
     * Lookup definitions for this resource's fields.
     * Override to return ['field_id' => LookupDefinition::make(...), ...]
     */
    public static function lookups(): array
    {
        return [];
    }

    /**
     * Screen actions available on card/headerlines/grid screens.
     * Override to return Action[].
     */
    public static function screenActions(?Model $model = null): array
    {
        return [];
    }

    /**
     * Handle a screen action execution.
     */
    public static function handleAction(string $actionId, ?Model $model, array $fields): array
    {
        // Find the action in screenActions
        foreach (static::screenActions($model) as $action) {
            if ($action->getId() === $actionId) {
                return $action->execute($model, $fields)->toArray();
            }
        }

        return [
            'success' => false,
            'message' => null,
            'error' => 'Action not implemented.',
            'screen' => null,
        ];
    }

    /**
     * Layout type: 'Card', 'HeaderLines', or 'Grid'.
     */
    public static function layout(): string
    {
        return 'Card';
    }

    /**
     * Column definitions for the grid/line portion of HeaderLines/Grid layouts.
     * @return \TwoWee\Laravel\Columns\Column[]
     */
    public static function lineColumns(): array
    {
        return [];
    }

    /**
     * Eloquent relationship name for header-lines line items.
     */
    public static function linesRelation(): ?string
    {
        return null;
    }

    /**
     * Overlay percentage for HeaderLines layout (0-100).
     */
    public static function linesOverlayPct(): int
    {
        return 50;
    }

    /**
     * Whether the lines overlay opens automatically on HeaderLines screens.
     */
    public static function linesOpen(): bool
    {
        return false;
    }

    /**
     * Footer totals definitions for Grid/HeaderLines.
     * Return array of ['label' => '...', 'value' => '...', 'source_column' => '...', 'aggregate' => 'sum']
     */
    public static function totals(?Model $model = null): array
    {
        return [];
    }

    /**
     * Build the protocol envelope shared by all ScreenContract responses.
     * Public so MenuController (which isn't a Resource) can use it.
     */
    public static function envelope($user = null): array
    {
        $workDate = config('twowee.work_date');
        if ($workDate === null) {
            $dateFormat = config('twowee.locale.date_format', 'DD-MM-YYYY');
            $phpFormat = str_replace(
                ['DD', 'MM', 'YYYY', 'YY'],
                ['d', 'm', 'Y', 'y'],
                $dateFormat
            );
            $workDate = date($phpFormat);
        }

        $displayName = $user?->name
            ?? Auth::guard('twowee')->user()?->name
            ?? null;

        return [
            'locale' => config('twowee.locale', [
                'date_format' => 'DD-MM-YYYY',
                'decimal_separator' => ',',
                'thousand_separator' => '.',
            ]),
            'work_date' => $workDate,
            'ui_strings' => (object) config('twowee.ui_strings', []),
            'user_display_name' => $displayName,
        ];
    }


    protected static function buildUrls(string $recordId = 'new'): array
    {
        $base = \TwoWee\Laravel\TwoWee::baseUrl();
        $slug = static::getSlug();

        $actions = [
            'save' => $base . '/screen/' . $slug . '/card/' . $recordId . '/save',
            'delete' => $base . '/screen/' . $slug . '/card/' . $recordId . '/delete',
            'create' => $base . '/screen/' . $slug . '/card/new',
        ];

        if ($recordId === 'new') {
            $actions['save'] = $base . '/screen/' . $slug . '/card/new';
            unset($actions['delete']);
        }

        return $actions;
    }

    protected static function buildScreenActions(?Model $model = null): array
    {
        $actions = static::screenActions($model);
        if (empty($actions)) {
            return [];
        }

        $base = \TwoWee\Laravel\TwoWee::baseUrl();
        $slug = static::getSlug();
        $recordId = $model !== null ? static::getRecordKeyValue($model) : null;

        $result = [];
        foreach ($actions as $action) {
            // Skip hidden actions
            if ($action instanceof \TwoWee\Laravel\Actions\Action && ! $action->shouldShow($model)) {
                continue;
            }

            // Auto-generate endpoint URL
            $endpoint = $recordId !== null
                ? $base . '/action/' . $slug . '/' . $recordId . '/' . $action->getId()
                : $base . '/action/' . $slug . '/' . $action->getId();

            $result[] = $action->toArray($model, $endpoint);
        }

        return $result;
    }

    public static function toCardJson(Model $model, ?string $status = null): array
    {
        $slug = static::getSlug();
        Field::setResourceSlug($slug);
        Column::setResourceSlug($slug);

        $sections = [];

        foreach (static::form() as $section) {
            $sections[] = $section->toArray($model);
        }

        $keyValue = static::getRecordKeyValue($model);
        $layout = static::layout();

        $result = array_merge(static::envelope(), [
            'layout' => $layout,
            'screen_id' => static::getScreenId(),
            'record_id' => $keyValue,
            'parent_url' => static::listUrl(),
            'title' => static::title($model),
            'sections' => $sections,
            'actions' => static::buildUrls($keyValue),
            'status' => $status,
            'screen_actions' => static::buildScreenActions($model),
        ]);

        // HeaderLines support
        if ($layout === 'HeaderLines') {
            $result['lines'] = static::buildLinesData($model);
            $result['lines_overlay_pct'] = static::linesOverlayPct();
            if (static::linesOpen()) {
                $result['lines_open'] = true;
            }
            $totals = static::totals($model);
            if (! empty($totals)) {
                $result['totals'] = $totals;
            }
        }

        return $result;
    }

    public static function toCreateJson(?string $status = null): array
    {
        $slug = static::getSlug();
        Field::setResourceSlug($slug);
        Column::setResourceSlug($slug);

        $sections = [];

        foreach (static::form() as $section) {
            $sections[] = $section->toArray(null);
        }

        $layout = static::layout();

        $result = array_merge(static::envelope(), [
            'layout' => $layout,
            'screen_id' => static::getScreenId(),
            'parent_url' => static::listUrl(),
            'title' => 'New ' . static::getLabel(),
            'sections' => $sections,
            'actions' => static::buildUrls('new'),
            'status' => $status,
            'screen_actions' => [],
        ]);

        // HeaderLines support
        if ($layout === 'HeaderLines') {
            $result['lines'] = static::buildEmptyLinesData();
            $result['lines_overlay_pct'] = static::linesOverlayPct();
            if (static::linesOpen()) {
                $result['lines_open'] = true;
            }
        }

        return $result;
    }

    public static function toListJson(iterable $rows, int $rowCount, ?string $status = null): array
    {
        Column::setResourceSlug(static::getSlug());
        $columns = static::table();
        $base = \TwoWee\Laravel\TwoWee::baseUrl();
        $slug = static::getSlug();
        $recordKey = static::getRecordKey();

        // Find which column index holds the record key
        $keyIndex = 0;
        foreach ($columns as $i => $column) {
            if ($column->getName() === $recordKey) {
                $keyIndex = $i;
                break;
            }
        }

        $columnDefs = array_map(fn ($col) => $col->toArray(), $columns);

        $rowData = \TwoWee\Laravel\Support\FieldHelpers::formatRows($rows, $columns);

        return array_merge(static::envelope(), [
            'layout' => 'List',
            'screen_id' => static::getScreenId(),
            'parent_url' => static::parentUrl(),
            'title' => static::getLabel() . ' List',
            'sections' => [],
            'lines' => [
                'columns' => $columnDefs,
                'rows' => $rowData,
                'row_count' => $rowCount,
                'selectable' => true,
                'on_select' => $base . '/screen/' . $slug . '/card/{' . $keyIndex . '}',
            ],
            'actions' => [
                'create' => $base . '/screen/' . $slug . '/card/new',
            ],
            'status' => $status,
        ]);
    }

    public static function toGridJson(?string $status = null): array
    {
        Column::setResourceSlug(static::getSlug());
        $columns = static::lineColumns();

        $columnDefs = array_map(fn ($col) => $col->toArray(), $columns);

        // Start with one empty row
        $rows = [
            [
                'index' => 0,
                'values' => array_fill(0, count($columns), ''),
            ],
        ];

        $result = array_merge(static::envelope(), [
            'layout' => 'Grid',
            'screen_id' => static::getScreenId(),
            'parent_url' => static::parentUrl(),
            'title' => static::getLabel(),
            'sections' => [],
            'lines' => [
                'columns' => $columnDefs,
                'rows' => $rows,
                'row_count' => 0,
                'selectable' => false,
            ],
            'actions' => static::buildUrls('new'),
            'status' => $status,
            'screen_actions' => static::buildScreenActions(),
        ]);

        $totals = static::totals();
        if (! empty($totals)) {
            $result['totals'] = $totals;
        }

        return $result;
    }

    protected static function buildLinesData(Model $model): array
    {
        $columns = static::lineColumns();
        $relation = static::linesRelation();

        $columnDefs = array_map(fn ($col) => $col->toArray(), $columns);

        $rowData = [];

        if ($relation !== null && method_exists($model, $relation)) {
            $rowData = \TwoWee\Laravel\Support\FieldHelpers::formatRows($model->{$relation}, $columns);
        }

        // Add one empty trailing row for new entries
        $rowData[] = [
            'index' => count($rowData),
            'values' => array_fill(0, count($columns), ''),
        ];

        return [
            'columns' => $columnDefs,
            'rows' => $rowData,
            'row_count' => count($rowData) - 1,
            'selectable' => false,
        ];
    }

    protected static function buildEmptyLinesData(): array
    {
        $columns = static::lineColumns();

        return [
            'columns' => array_map(fn ($col) => $col->toArray(), $columns),
            'rows' => [
                [
                    'index' => 0,
                    'values' => array_fill(0, count($columns), ''),
                ],
            ],
            'row_count' => 0,
            'selectable' => false,
        ];
    }

    /**
     * Resolve search columns for ?query= filtering.
     */
    public static function resolveSearchColumns(): array
    {
        $explicit = static::searchColumns();
        if ($explicit !== null) {
            return $explicit;
        }

        // Default: all Text columns
        $columns = [];
        foreach (static::table() as $column) {
            if ($column instanceof TextColumn) {
                $columns[] = $column->getName();
            }
        }

        return $columns;
    }
}
