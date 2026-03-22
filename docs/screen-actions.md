# Screen Actions

Screen actions are custom operations beyond save and delete — send an email, post a journal, export a CSV, change a status. The user triggers them with Ctrl+A.

## Quick Start

Define actions in your resource's `screenActions()` method. Each action is self-contained — its definition, form, and handler are all in one place:

```php
use TwoWee\Laravel\Actions\Action;
use TwoWee\Laravel\Actions\ActionField;
use TwoWee\Laravel\Actions\ActionResult;

public static function screenActions(?\Illuminate\Database\Eloquent\Model $model = null): array
{
    return [
        // Simple — executes immediately
        Action::make('export_csv')
            ->label('Export to CSV')
            ->action(fn ($record) => ActionResult::success('CSV exported.')),

        // Confirm — asks before executing
        Action::make('post')
            ->label('Post Journal')
            ->requiresConfirmation('Post all lines? This cannot be undone.')
            ->action(fn ($record) => ActionResult::success('Posted.')),

        // Modal — collects input, then executes
        Action::make('send_email')
            ->label('Send as Email')
            ->form(fn ($record) => [
                ActionField::make('to')->label('To')->email()
                    ->value($record?->email ?? '')->required(),
                ActionField::make('subject')->label('Subject')
                    ->value('Order ' . ($record?->no ?? '')),
            ])
            ->action(fn ($record, $data) => ActionResult::success('Sent to ' . $data['to'])),
    ];
}
```

That's it. No endpoint URLs, no separate handler method, no routing.

## Action Builder API

| Method | Purpose |
|--------|---------|
| `Action::make('id')` | Create an action with a unique ID |
| `->label('Text')` | Display text in the action picker |
| `->action(fn ($record, $data) => ...)` | The handler — runs when the action executes |
| `->requiresConfirmation(?string)` | Show Yes/No dialog before executing |
| `->form(array\|\Closure)` | Show a modal form before executing |
| `->visible(bool\|\Closure)` | Conditionally show the action |
| `->hidden(bool\|\Closure)` | Conditionally hide the action |

## Action Kinds

### Simple (default)

Executes immediately when the user selects it. No confirmation, no form.

```php
Action::make('recalculate')
    ->label('Recalculate Totals')
    ->action(function ($record) {
        $record->recalculate();
        return ActionResult::success('Totals recalculated.');
    })
```

### Confirm

Shows a Yes/No dialog. The user must confirm before the action runs. Use this for destructive or irreversible operations.

```php
Action::make('post')
    ->label('Post Journal')
    ->requiresConfirmation('Post all journal lines? This cannot be undone.')
    ->action(function ($record) {
        $record->post();
        return ActionResult::success('Journal posted.');
    })
```

If no message is passed to `requiresConfirmation()`, the client shows "Execute this action?".

### Modal

Shows a form with input fields. The user fills them in and submits. The field values arrive in the `$data` parameter of the `action()` callback.

```php
Action::make('change_status')
    ->label('Change Status')
    ->form([
        ActionField::make('status')->label('New Status')
            ->option(['Draft', 'Confirmed', 'Released'])->required(),
        ActionField::make('reason')->label('Reason')
            ->placeholder('Optional reason for the change'),
    ])
    ->action(function ($record, $data) {
        $record->update(['status' => $data['status']]);
        return ActionResult::success('Status changed to ' . $data['status']);
    })
```

## Dynamic Form Fields

Use a closure to pre-fill values from the record. The closure receives the model:

```php
Action::make('send_email')
    ->label('Send as Email')
    ->form(fn ($record) => [
        ActionField::make('to')->label('To')->email()
            ->value($record?->customer_email ?? '')->required(),
        ActionField::make('cc')->label('CC')->email()
            ->value($record?->salesperson_email ?? ''),
        ActionField::make('subject')->label('Subject')
            ->value('Sales Order ' . ($record?->no ?? '')),
        ActionField::make('message')->label('Message'),
    ])
    ->action(function ($record, $data) {
        Mail::to($data['to'])
            ->cc(array_filter([$data['cc']]))
            ->send(new SalesOrderMail($record, $data));

        return ActionResult::success('Email sent to ' . $data['to']);
    })
```

## ActionResult

The `action()` callback returns an `ActionResult`. The client shows the message in a dialog the user must acknowledge.

```php
// Success — shows message, refreshes current screen
return ActionResult::success('Email sent.');

// Success with screen update — replaces the current screen
return ActionResult::success('Journal posted.', static::toGridJson('Posted.'));

// Success with redirect — navigates to a different screen, clears history
return ActionResult::success('Invoice posted.')
    ->redirectTo(PostedInvoiceResource::cardUrl($postedInvoice->no));

// Error — shows error, user can retry
return ActionResult::error('A valid email address is required.');
```

You can also return a plain string (treated as success) for brevity:

```php
->action(fn ($record) => 'CSV exported.')
```

### Post-action navigation

What happens after a successful action depends on what you return:

| Return | Client behavior |
|--------|----------------|
| `ActionResult::success('Done.')` | Refreshes current screen. If 404 (record deleted), pops back to list. |
| `ActionResult::success('Done.', $screen)` | Replaces current screen with the provided ScreenContract. |
| `->redirectTo($url)` | Clears history, navigates to the screen. Escape follows `parent_url`. |
| `->redirectTo($externalUrl)` | External URL (`https://`): opens in system browser. |
| `->pushTo($url)` | Pushes screen onto stack. Escape returns to current screen. |

### Redirect to another screen

Use `redirectTo()` when an action transforms the current record — posting a draft creates a posted record, the draft is gone:

```php
Action::make('post')
    ->label('Post Invoice')
    ->requiresConfirmation('Post this invoice?')
    ->action(function ($record) {
        $posted = resolve(PostSalesInvoice::class)->handle($record);

        return ActionResult::success('Invoice posted.')
            ->redirectTo(PostedInvoiceResource::cardUrl($posted->no));
    })
```

Use `Resource::cardUrl($id)` and `Resource::listUrl()` to build internal URLs — they include the prefix automatically.

### Open an external URL

`redirectTo()` also works with external URLs. The client detects `http://` or `https://` and opens the system browser instead of fetching a screen:

```php
Action::make('view_in_portal')
    ->label('View in Portal')
    ->action(fn ($record) => ActionResult::success('Opening...')
        ->redirectTo('https://portal.example.com/invoice/' . $record->no))
```

### Push to a related screen

Use `pushTo()` when the action opens a related screen without destroying the current one — Escape returns to where you were:

```php
Action::make('view_report')
    ->label('View Report')
    ->action(fn ($record) => ActionResult::success('Report ready.')
        ->pushTo(InvoiceReportResource::cardUrl($record->no)))
```

`redirectTo()` = destructive (history cleared, record gone).
`pushTo()` = non-destructive (stacked, Escape returns).

## ActionField

Modal forms use `ActionField` for input. Each type has its own method:

```php
ActionField::make('to')
    ->label('To')
    ->email()                 // Sets type to Email
    ->value('')
    ->required()
    ->placeholder('email@example.com')
```

### Type methods

| Method | Type |
|--------|------|
| `->text()` | Text (default) |
| `->email()` | Email |
| `->decimal()` | Decimal |
| `->integer()` | Integer |
| `->date()` | Date |
| `->time()` | Time |
| `->phone()` | Phone |
| `->url()` | URL |
| `->boolean()` | Boolean |
| `->password()` | Password |
| `->textarea(int $rows = 4)` | TextArea |
| `->option([...])` | Option (sets type and options in one call) |

### Common methods

| Method | Purpose |
|--------|---------|
| `->label('Text')` | Display label |
| `->value('default')` | Pre-filled value |
| `->required()` | Mark as required |
| `->placeholder('hint')` | Dimmed hint text |
| `->options([...])` | Choices (for Option type) |
| `->validation([...])` | Client-side validation rules |

## Conditional Actions

Show or hide actions based on the record's state:

```php
// Only show "Post" on draft documents
Action::make('post')
    ->label('Post')
    ->visible(fn ($record) => $record?->status === 'draft')
    ->requiresConfirmation()
    ->action(fn ($record) => ...),

// Only show "Void" on posted documents
Action::make('void')
    ->label('Void')
    ->visible(fn ($record) => $record?->status === 'posted')
    ->requiresConfirmation('Void this document? This creates a reversal entry.')
    ->action(fn ($record) => ...),

// Always hidden (useful for temporarily disabling)
Action::make('archive')
    ->label('Archive')
    ->hidden()
    ->action(fn ($record) => ...),
```

## Complete Example: Sales Order Actions

```php
public static function screenActions(?\Illuminate\Database\Eloquent\Model $model = null): array
{
    return [
        Action::make('send_email')
            ->label('Send as Email')
            ->visible(fn ($record) => $record?->status !== 'draft')
            ->form(fn ($record) => [
                ActionField::make('to')->label('To')->email()
                    ->value($record?->customer_email ?? '')->required(),
                ActionField::make('subject')->label('Subject')
                    ->value('Sales Order ' . ($record?->no ?? '')),
            ])
            ->action(function ($record, $data) {
                // Mail::to($data['to'])->send(new SalesOrderMail($record, $data));
                return ActionResult::success('Email sent to ' . $data['to']);
            }),

        Action::make('release')
            ->label('Release')
            ->visible(fn ($record) => $record?->status === 'open')
            ->requiresConfirmation('Release this order for shipping?')
            ->action(function ($record) {
                $record->update(['status' => 'released']);
                return ActionResult::success('Order released.');
            }),

        Action::make('reopen')
            ->label('Reopen')
            ->visible(fn ($record) => $record?->status === 'released')
            ->action(function ($record) {
                $record->update(['status' => 'open']);
                return ActionResult::success('Order reopened.');
            }),

        Action::make('print')
            ->label('Print Order')
            ->action(fn ($record) => ActionResult::success('Order sent to printer.')),
    ];
}
```

## How It Works

1. Your resource returns `Action` instances from `screenActions()`
2. The plugin auto-generates endpoint URLs from the resource slug + record ID
3. The client shows `Ctrl+A Actions` in the bottom bar when actions exist
4. The user presses Ctrl+A (or F8) to open the action picker
5. The picker shows all visible actions with their labels
6. Based on the kind: executes (simple), confirms (confirm), or collects input (modal)
7. The client POSTs to the auto-generated endpoint
8. The plugin finds the matching `Action` and calls its `action()` callback
9. The client shows the result message in a dialog the user must dismiss
