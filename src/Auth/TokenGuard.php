<?php

namespace TwoWee\Laravel\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TokenGuard implements Guard
{
    protected ?Authenticatable $user = null;

    public function __construct(
        protected Request $request,
    ) {}

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function user(): ?Authenticatable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->request->bearerToken();

        if ($token === null) {
            return null;
        }

        $record = DB::table('twowee_tokens')
            ->where('token', hash('sha256', $token))
            ->first();

        if ($record === null) {
            return null;
        }

        $provider = config('auth.providers.users.model');
        $this->user = $provider::find($record->tokenable_id);

        if ($this->user !== null) {
            DB::table('twowee_tokens')
                ->where('id', $record->id)
                ->update(['last_used_at' => now()]);
        }

        return $this->user;
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    public function setUser(Authenticatable $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Create a new token for the given user.
     *
     * @return string The plain-text token (store this client-side).
     */
    public static function createToken(Authenticatable $user): string
    {
        $plainText = bin2hex(random_bytes(32));

        DB::table('twowee_tokens')->insert([
            'tokenable_type' => get_class($user),
            'tokenable_id' => $user->getAuthIdentifier(),
            'token' => hash('sha256', $plainText),
            'created_at' => now(),
        ]);

        return $plainText;
    }

    /**
     * Revoke all tokens for the given user.
     */
    public static function revokeTokens(Authenticatable $user): void
    {
        DB::table('twowee_tokens')
            ->where('tokenable_type', get_class($user))
            ->where('tokenable_id', $user->getAuthIdentifier())
            ->delete();
    }
}
