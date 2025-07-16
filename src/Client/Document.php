<?php

namespace Sinemah\CouchEloquent\Client;

use Exception;
use Illuminate\Support\Arr;

class Document
{
    private ?string $id;
    private ?string $rev;

    private Builder $builder;

    public static function load($values): Document
    {
        return new self($values);
    }

    private function __construct(private $values)
    {
        $this->builder = Builder::load(new Connection());

        $this->id = $values['_id'] ?? null;
        $this->rev = $values['_rev'] ?? null;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function rev(): string
    {
        return $this->rev;
    }

    public function find(string $database): bool
    {
        $doc = $this->builder->find($database, $this->values['_id'] ?? null) ?? [];

        $this->values = array_merge(
            $this->values,
            $doc
        );

        return count($doc) > 0;
    }

    public function create(string $database): bool
    {
        if ($values = $this->builder->create($database, $this->toArray())) {
            $this->values = $values;
            $this->id = $values['_id'];
            $this->rev = $values['_rev'];

            return true;
        }

        return false;
    }

    public function update(string $database, array $payload = []): bool
    {
        $this->values = $payload;

        if ($values = $this->builder->update($database, $this->id(), $this->toArray())) {
            $this->values = $values;
            $this->rev = $values['_rev'];

            return true;
        }

        return false;
    }

    public function delete(string $database): bool
    {
        return $this->builder->delete($database, $this->id);
    }

    public function toArray(): array
    {
        return Arr::except($this->values, ['_id', '_rev']);
    }
}
