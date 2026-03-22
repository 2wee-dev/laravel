# Authentication

## Overview

TwoWee uses stateless bearer tokens stored as SHA256 hashes in the `twowee_tokens` database table. The client sends `Authorization: Bearer {token}` with every request.

## Login Flow

1. Client tries `GET /menu/main`
2. Server returns `401 { "error": "Unauthenticated" }`
3. Client fetches `GET /auth/login` → returns Card with `auth_action` URL
4. User fills in credentials, client POSTs to `auth_action`
5. Success: `{ "success": true, "token": "...", "screen": { menu } }`
6. Failure: `{ "success": false, "token": null, "error": "Invalid credentials.", "screen": null }` (401)
7. Client stores token for subsequent requests

## Login Field

Configure which field to use for login in `config/twowee.php`:

```php
'auth' => [
    'username_field' => 'email',   // or 'username'
],
```

## Protected Routes

All routes except `GET /auth/login` and `POST /auth/login` require authentication. Unauthenticated requests receive:

```json
{ "error": "Unauthenticated" }
```

with HTTP 401.

## Logout

`POST /auth/logout` revokes all tokens for the user and returns `{ "success": true }`.

## Token Storage

Tokens are stored hashed in `twowee_tokens`:
- `tokenable_type` / `tokenable_id`: polymorphic link to user
- `token`: SHA256 hash of the plaintext token
- `last_used_at`: updated on each request

The plaintext token is only returned once during login. The 64-character hex token is opaque — not a JWT.

## Custom User Model

The auth system uses the default user provider (`auth.providers.users.model`). Ensure your User model exists and has a `password` field with bcrypt-hashed values.
