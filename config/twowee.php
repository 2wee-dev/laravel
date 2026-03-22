<?php

return [

    'prefix' => env('TWOWEE_PREFIX', 'terminal'),

    // 'app_name' => env('TWOWEE_APP_NAME'), // Defaults to config('app.name')

    'terminal' => [
        // Port the two_wee_terminal service listens on.
        'port' => env('TWOWEE_TERMINAL_PORT', 7681),

        // Lock the terminal to a specific server URL.
        // If empty, users enter the server URL themselves on first connect.
        'server_url' => env('TWOWEE_TERMINAL_SERVER', ''),

        // URL to redirect to when the user quits the terminal application.
        // If empty, a "session ended" message is shown with a reload prompt.
        'quit_url' => env('TWOWEE_QUIT_URL', ''),
    ],

    'resources' => [
        // Auto-discovered from app/TwoWee/Resources/
    ],

    'auth' => [
        'enabled' => false,
        'username_field' => 'email',
    ],

    'menu' => [
        'default_tab' => 'Home',
        'tab_order' => [],  // e.g. ['Home', 'Sales', 'Purchasing', 'Finance']
        'items' => [
            // Custom menu items (placeholders, sub-menus, external links)
            // ['label' => 'Reports', 'group' => 'Finance', 'sort' => 99, 'action' => ['type' => 'message', 'text' => 'Coming soon.']],
            // ['label' => 'Setup', 'group' => 'Admin', 'sort' => 1, 'action' => ['type' => 'open_menu', 'url' => '/terminal/menu/setup']],
        ],
    ],

    'lookup' => [
        'page_size' => 50,
    ],

    'locale' => [
        'date_format' => 'DD-MM-YYYY',
        'decimal_separator' => ',',
        'thousand_separator' => '.',
    ],

    'work_date' => null, // null = today

    'ui_strings' => [
        'save_confirm_title' => 'Save Changes?',
        'save_confirm_message' => 'Do you want to save your changes?',
        'save_confirm_save' => 'Save',
        'save_confirm_discard' => 'Discard',
        'save_confirm_cancel' => 'Cancel',
        'quit_title' => 'Quit',
        'quit_message' => 'Are you sure you want to quit?',
        'quit_yes' => 'Yes',
        'quit_no' => 'No',
        'logout' => 'Logout',
        'saved' => 'Saved.',
        'saving' => 'Saving...',
        'loading' => 'Loading...',
        'created' => 'Created.',
        'deleted' => 'Deleted.',
        'cancelled' => 'Cancelled.',
        'no_changes' => 'No changes.',
        'copied' => 'Copied.',
        'error_prefix' => 'Error: ',
        'save_error_prefix' => 'Save error: ',
        'login_error' => 'Login failed.',
        'server_unavailable' => 'Server unavailable.',
        'connecting' => 'Connecting...',
    ],

];
