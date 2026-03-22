<?php

namespace TwoWee\Laravel\Actions;

use Illuminate\Database\Eloquent\Model;

class Action
{
    protected string $id;

    protected ?string $label = null;

    protected string $kind = 'simple';

    protected ?string $confirmMessage = null;

    /** @var ActionField[] */
    protected array $fields = [];

    protected ?\Closure $actionCallback = null;

    protected ?\Closure $fieldsCallback = null;

    protected ?\Closure $visibleCallback = null;

    protected bool $isVisible = true;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public static function make(string $id): static
    {
        return new static($id);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Set the action handler. Receives the model and form field values.
     *
     *   Action::make('send_email')
     *       ->action(fn (Model $record, array $data) => ...)
     */
    public function action(\Closure $callback): static
    {
        $this->actionCallback = $callback;

        return $this;
    }

    /**
     * Show a confirmation dialog before executing.
     */
    public function requiresConfirmation(?string $message = null): static
    {
        $this->kind = 'confirm';
        $this->confirmMessage = $message;

        return $this;
    }

    /**
     * Show a modal form before executing. Fields can be static or a closure
     * receiving the model.
     *
     *   ->form([ActionField::make('to')->label('To')->email()])
     *   ->form(fn (Model $record) => [
     *       ActionField::make('to')->label('To')->value($record->email),
     *   ])
     */
    public function form(array|\Closure $fields): static
    {
        $this->kind = 'modal';

        if ($fields instanceof \Closure) {
            $this->fieldsCallback = $fields;
        } else {
            $this->fields = $fields;
        }

        return $this;
    }

    /**
     * Conditionally show this action.
     */
    public function visible(bool|\Closure $condition = true): static
    {
        if ($condition instanceof \Closure) {
            $this->visibleCallback = $condition;
        } else {
            $this->isVisible = $condition;
        }

        return $this;
    }

    /**
     * Conditionally hide this action.
     */
    public function hidden(bool|\Closure $condition = true): static
    {
        if ($condition instanceof \Closure) {
            $this->visibleCallback = fn ($model) => ! ($condition)($model);
        } else {
            $this->isVisible = ! $condition;
        }

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Check if this action should be shown for the given model.
     */
    public function shouldShow(?Model $model = null): bool
    {
        if ($this->visibleCallback !== null) {
            return (bool) ($this->visibleCallback)($model);
        }

        return $this->isVisible;
    }

    /**
     * Execute the action handler.
     */
    public function execute(?Model $model, array $data): ActionResult
    {
        if ($this->actionCallback === null) {
            return ActionResult::error('Action not implemented.');
        }

        $result = ($this->actionCallback)($model, $data);

        if ($result instanceof ActionResult) {
            return $result;
        }

        // Allow returning a plain string as success message
        if (is_string($result)) {
            return ActionResult::success($result);
        }

        // Allow returning an array (legacy format)
        if (is_array($result)) {
            return ($result['success'] ?? false)
                ? ActionResult::success($result['message'] ?? 'Done.', $result['screen'] ?? null)
                : ActionResult::error($result['error'] ?? 'Action failed.');
        }

        return ActionResult::success('Done.');
    }

    /**
     * Resolve fields for a given model (handles closure or static array).
     */
    public function resolveFields(?Model $model = null): array
    {
        if ($this->fieldsCallback !== null) {
            return ($this->fieldsCallback)($model);
        }

        return $this->fields;
    }

    /**
     * Build the JSON representation for the client.
     */
    public function toArray(?Model $model = null, ?string $endpoint = null): array
    {
        $result = [
            'id' => $this->id,
            'label' => $this->label ?? $this->id,
            'kind' => $this->kind,
            'endpoint' => $endpoint,
        ];

        if ($this->kind === 'confirm' && $this->confirmMessage !== null) {
            $result['confirm_message'] = $this->confirmMessage;
        }

        if ($this->kind === 'modal') {
            $fields = $this->resolveFields($model);
            if (! empty($fields)) {
                $result['fields'] = array_map(fn ($f) => $f->toArray(), $fields);
            }
        }

        return $result;
    }
}
