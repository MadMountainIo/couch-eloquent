<?php

namespace Sinemah\CouchEloquent\Query\Parser;

use Sinemah\CouchEloquent\Query\Collection;
use Sinemah\CouchEloquent\Query\Parser\Query\DTO;

class QueryFactory
{
    protected DTO $data;
    protected array $orders;
    protected array $fields = [];
    protected int $limit = 50;
    protected int $offset = 0;

    public static function load(Collection $wheres, array $orders, ?array $fields = [], int $limit = 50, int $offset = 0): QueryFactory
    {
        return new self($wheres->toQuery(), $orders, $fields, $limit, $offset);
    }

    private function __construct(?array $selector, $orders, ?array $fields = [], int $limit = 50, int $offset = 0)
    {
        $this->data = new DTO(['selector' => $selector]);
        $this->fields = $fields ?? [];
        $this->orders = $orders;
        $this->limit = $limit;
        $this->offset = $offset;
    }

    public function toQuery(): ?array
    {
        $query = collect();

        if (!!$this->orders) {
            $query->put('sort', array_map(fn($key) => [$key => $this->orders[$key]], array_keys($this->orders)));
        }

        if ($this->data->selector != null) {
            $query->put('selector', $this->data->selector);
        }

        if ($query->has('sort') && !$query->has('selector')) {
            $query->put('selector', (object)[]);
        }

        if (!empty($this->fields)) {
            $query->put('fields', $this->fields);
        }

        if ($this->limit > 0) {
            $query->put('limit', $this->limit);
        }

        if ($this->offset > 0) {
            $query->put('skip', $this->offset);
        }

        return $query->isEmpty() ? null : $query->toArray();
    }
}
