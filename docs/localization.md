# Localization

## Locale Settings

Configure in `config/twowee.php`:

```php
'locale' => [
    'date_format' => 'DD-MM-YYYY',     // or 'MM-DD-YYYY'
    'decimal_separator' => ',',         // or '.'
    'thousand_separator' => '.',        // or ','
],
```

The locale is included in every ScreenContract response, so the client knows how to parse and display values.

## Work Date

The work date is a reference date for date field shortcuts. When the user types `t` in a date field, it means the work date (not necessarily today).

```php
'work_date' => null,    // null = today (formatted using locale)
'work_date' => '15-03-2026',  // Fixed date
```

This is useful for accounting periods where you want all entries to default to a specific date.

## Number Formatting

The locale config tells the **client** how to format numbers for display:

- `decimal_separator: ','` + `thousand_separator: '.'` → client displays `1.234,50` (European)
- `decimal_separator: '.'` + `thousand_separator: ','` → client displays `1,234.50` (US)

The **server** works with plain English numbers (period decimal, no thousand separator). The wire format between server and client is always plain numbers like `"1234.5"`. The client handles all locale formatting internally.

If you change the locale config, the client adjusts its display automatically — the server doesn't need to change anything.

## UI Strings

Override any client UI text:

```php
'ui_strings' => [
    'save_confirm_title' => 'Vista breytingar?',
    'save_confirm_save' => 'Vista',
    'save_confirm_discard' => 'Henda',
    'logout' => 'Útskrá',
    'saved' => 'Vistað.',
    'deleted' => 'Eytt.',
    'loading' => 'Hleður...',
],
```

Missing keys use English defaults. The full list of available keys:

| Key | Default |
|-----|---------|
| `save_confirm_title` | Save Changes? |
| `save_confirm_message` | You have unsaved changes. |
| `save_confirm_save` | Save |
| `save_confirm_discard` | Discard |
| `save_confirm_cancel` | Cancel |
| `quit_title` | Quit |
| `quit_message` | Do you want to quit? |
| `quit_yes` | Yes |
| `quit_no` | No |
| `logout` | Log Out |
| `saved` | Saved. |
| `saving` | Saving... |
| `loading` | Loading... |
| `created` | Created. |
| `deleted` | Deleted. |
| `cancelled` | Cancelled. |
| `no_changes` | No changes. |
| `copied` | Copied. |
| `error_prefix` | Error |
| `save_error_prefix` | Save error |
| `login_error` | Invalid username or password. |
| `server_unavailable` | Server unavailable. |
| `connecting` | Connecting... |
