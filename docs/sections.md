# Sections

Sections group fields on Card and HeaderLines screens. They are positioned on a 2D grid using `column` and `rowGroup`.

## Usage

```php
public static function form(): array
{
    return [
        // Left column, first row group
        Section::make('General')
            ->column(0)->rowGroup(0)
            ->fields([
                Text::make('no')->label('No.')->uppercase(),
                Text::make('name')->label('Name'),
            ]),

        // Right column, first row group (side-by-side with General)
        Section::make('Contact')
            ->column(1)->rowGroup(0)
            ->fields([
                Email::make('email')->label('E-Mail'),
                Phone::make('phone')->label('Phone'),
            ]),

        // Full width, second row group (below both)
        Section::make('Notes')
            ->column(0)->rowGroup(1)
            ->fields([
                TextArea::make('notes')->label('Notes'),
            ]),
    ];
}
```

## Layout Grid

- `column(0)` = left, `column(1)` = right
- `rowGroup(0)` = first row, `rowGroup(1)` = second row, etc.
- Sections in the same row group appear side-by-side
- Sections in different row groups stack vertically

### Convenience shortcuts

| Method | Equivalent | Purpose |
|--------|-----------|---------|
| `->left()` | `->column(0)` | Place section in the left column |
| `->right()` | `->column(1)` | Place section in the right column |
| `->fullWidth()` | `->column(0)` | Full-width section (use with its own `rowGroup`) |

```php
Section::make('General')->left()->rowGroup(0)->fields([...]),
Section::make('Contact')->right()->rowGroup(0)->fields([...]),
Section::make('Notes')->fullWidth()->rowGroup(1)->fields([...]),
```

## Section ID

Auto-generated from the label using `Str::snake()`:
- "General" → `general`
- "Invoice Details" → `invoice_details`

## Hidden Fields

Fields marked with `->hidden()` are excluded from the JSON output but still exist in the PHP definition. Useful for fields used only in server-side logic.
