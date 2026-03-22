<?php

namespace TwoWee\Laravel\Fields\Concerns;

trait HasRelationship
{
    protected ?string $relationshipName = null;

    protected ?string $relationshipTitleAttribute = null;

    /**
     * Auto-wire a lookup from a BelongsTo relationship.
     * Generates a LookupDefinition automatically during resource boot.
     */
    public function relationship(string $name, string $titleAttribute): static
    {
        $this->relationshipName = $name;
        $this->relationshipTitleAttribute = $titleAttribute;

        // Enable blur validation and lookup endpoint
        $this->blurValidate = true;
        $this->lookupEndpoint = $this->name;
        $this->lookupDisplayField = $titleAttribute;
        $this->lookupValidate = true;

        return $this;
    }

    public function getRelationshipName(): ?string
    {
        return $this->relationshipName;
    }

    public function getRelationshipTitleAttribute(): ?string
    {
        return $this->relationshipTitleAttribute;
    }

    public function hasRelationship(): bool
    {
        return $this->relationshipName !== null;
    }

    /**
     * Resolve a target (model class or relationship string) to a relationship name.
     *
     * For class targets: scans the model's methods for those with a Relation return type,
     * invokes them to find which returns an instance of the target model.
     * Only methods with explicit Relation return types are invoked — this is safe because
     * Eloquent relationship methods never have side effects.
     *
     * Requires a real model instance with a database connection (call at render/request time, not boot).
     */
    public static function resolveRelationNameStatic(mixed $model, string $target): string
    {
        // If target is not a class name, it's a relationship string — use directly
        if (! class_exists($target)) {
            return $target;
        }

        // Target is a model class — find the relationship that returns it
        $matches = [];
        $reflection = new \ReflectionClass($model);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getNumberOfParameters() !== 0 || $method->isStatic()) {
                continue;
            }

            // Only invoke methods with an explicit Relation return type
            $returnType = $method->getReturnType();
            if (! $returnType instanceof \ReflectionNamedType) {
                continue;
            }
            if (! is_a($returnType->getName(), \Illuminate\Database\Eloquent\Relations\Relation::class, true)) {
                continue;
            }

            try {
                $result = $method->invoke($model);
                if (get_class($result->getRelated()) === $target) {
                    $matches[] = $method->getName();
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) === 0) {
            throw new \RuntimeException(
                "No relationship returning {$target} found on " . get_class($model)
                . ". Ensure the relationship method has a return type (e.g. ': HasMany'),"
                . " or use the relationship name string instead."
            );
        }

        throw new \RuntimeException(
            "Multiple relationships returning {$target} found on " . get_class($model)
            . ": " . implode(', ', $matches)
            . ". Use the relationship name string instead."
        );
    }
}
