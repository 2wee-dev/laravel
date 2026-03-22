<?php

namespace TwoWee\Laravel\Columns;

class DecimalColumn extends Column
{
    protected int $decimals = 2;

    protected function columnType(): string
    {
        return 'Decimal';
    }

    public function decimals(int $decimals): static
    {
        $this->decimals = $decimals;

        return $this;
    }

    public function toArray(): array
    {
        $result = parent::toArray();

        $result['validation'] = array_merge($result['validation'] ?? [], [
            'decimals' => $this->decimals,
        ]);

        return $result;
    }

    public function parseValue(string $value): mixed
    {
        if ($value === '') {
            return null;
        }

        return (float) $value;
    }

    public function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $formatted = number_format((float) $value, $this->decimals, '.', '');

        return $this->applyZeroDisplay($formatted);
    }
}
