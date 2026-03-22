<?php

namespace TwoWee\Laravel\Actions;

class ActionResult
{
    protected bool $success;

    protected ?string $message;

    protected ?string $error;

    protected ?array $screen;

    protected ?string $redirectUrl;

    protected ?string $pushUrl;

    private function __construct(bool $success, ?string $message = null, ?string $error = null, ?array $screen = null)
    {
        $this->success = $success;
        $this->message = $message;
        $this->error = $error;
        $this->screen = $screen;
        $this->redirectUrl = null;
        $this->pushUrl = null;
    }

    public static function success(string $message, ?array $screen = null): static
    {
        return new static(true, $message, null, $screen);
    }

    public static function error(string $error): static
    {
        return new static(false, null, $error);
    }

    /**
     * Redirect to a different screen after the action.
     * Clears navigation history — Escape follows the destination's parent_url.
     * Use when the action destroys the current record (e.g. posting an invoice).
     */
    public function redirectTo(string $url): static
    {
        $this->redirectUrl = $url;

        return $this;
    }

    /**
     * Push a screen onto the navigation stack after the action.
     * Escape returns to the current screen (non-destructive).
     * Use when the action opens a related screen (e.g. viewing a report).
     */
    public function pushTo(string $url): static
    {
        $this->pushUrl = $url;

        return $this;
    }

    public function toArray(): array
    {
        $result = [
            'success' => $this->success,
            'message' => $this->message,
            'error' => $this->error,
            'screen' => $this->screen,
        ];

        if ($this->redirectUrl !== null) {
            $result['redirect_url'] = $this->redirectUrl;
        }

        if ($this->pushUrl !== null) {
            $result['push_url'] = $this->pushUrl;
        }

        return $result;
    }
}
