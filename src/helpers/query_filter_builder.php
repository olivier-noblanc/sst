<?php
/** QueryFilterBuilder — Construit des WHERE dynamiques sans duplication. */

class QueryFilterBuilder
{
    private string $where = '1=1';
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

    public function addIn(string $column, array $values): self
    {
        if (empty($values)) {
            return $this;
        }
        $placeholders = [];
        foreach ($values as $value) {
            $paramKey = ':qf_' . $this->paramIndex++;
            $placeholders[] = $paramKey;
            $this->params[$paramKey] = $value;
        }
        $this->where .= " AND $column IN (" . implode(', ', $placeholders) . ')';
        return $this;
    }

    public function getWhere(): string { return $this->where; }
    public function getParams(): array { return $this->params; }
    public function build(): array { return ['where' => $this->where, 'params' => $this->params]; }
}
