<?php

/** QueryFilterBuilder — Construit des WHERE dynamiques sans duplication. */

namespace App\Query;

class QueryFilterBuilder
{
    private string $where = '1=1';
    /** @var array<string, mixed> */
    private array $params = [];
    private int $paramIndex = 0;

    public function addEqual(string $column, mixed $value): self
    {
        if ($value === null || $value === '' || $value === 0) {
            return $this;
        }
        $paramKey = ':qf_' . $this->paramIndex++;
        $this->where .= " AND $column = $paramKey";
        $this->params[$paramKey] = $value;
        return $this;
    }

    /** @param array<string, mixed> $params */
    public function addRaw(string $sqlFragment, array $params = []): self
    {
        $this->where .= " AND $sqlFragment";
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    /** @return array{where: string, params: array<string, mixed>} */
    public function build(): array
    {
        return ['where' => $this->where, 'params' => $this->params];
    }
}
