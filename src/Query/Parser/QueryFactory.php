<?php

namespace Sinemah\CouchEloquent\Query\Parser;

use Sinemah\CouchEloquent\Query\Collection;
use Sinemah\CouchEloquent\Query\Parser\Query\DTO;

class QueryFactory
{
    protected DTO $data;
    protected array $orders;

    public static function load(Collection $wheres, array $orders): QueryFactory
    {
        return new self($wheres->toQuery(), $orders);
    }

    private function __construct(?array $selector, $orders)
    {
        $this->data = new DTO(['selector' => $selector]);
        $this->orders = $orders;
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

        return $query->isEmpty() ? null : $query->toArray();
    }
}
