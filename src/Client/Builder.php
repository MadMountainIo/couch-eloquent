<?php

namespace Sinemah\CouchEloquent\Client;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Sinemah\CouchEloquent\Types\Date;

class Builder
{

    private function __construct(private readonly Connection $connection) {}

    public static function withConnection(): Builder
    {
        return self::load(new Connection());
    }

    public static function load(Connection $connection): Builder
    {
        return new self($connection);
    }

    public function find(string $database, string $id): ?array
    {
        $response = $this->request()->get($this->database($database, $id));

        if ($response->successful()) {
            return $response->json();
        }

        return null;
    }

    public function search(string $database, ?array $params = null): ?array
    {
        $response = match (true) {
            $params === null => $this->findAll($database),
            is_array($params) => $this->request()->post($this->database($database, '_find'), $params),
        };

        if ($response->successful()) {
            return $response->json('docs');
        }

        return null;
    }

    public function findAll(string $database): Response
    {
        return $this->request()->post($this->database($database, '_find'), [
            'selector' => [
                '$and' => [
                    [
                        '_id' => [
                            '$gt' => null
                        ]
                    ]
                ]
            ]
        ]);
    }

    public function create(string $database, array $values): ?array
    {
        $date = Date::load(now());
        $timestamps = [
            'created_at' => $date->toArray(),
            'updated_at' => $date->toArray(),
            'deleted_at' => null,
        ];
        $response = $this->request()->put(
            $this->database($database, $values['id'] ?? Str::uuid()),
            array_merge(
                Arr::except($values, ['id']),
                $timestamps,
            )
        );

        if ($response->json('ok')) {
            return array_merge(
                [
                    '_id' => $response->json('id'),
                    '_rev' => $response->json('rev'),
                ],
                $values,
                $timestamps,
            );
        }

        return null;
    }

    public function update(string $database, string $id, array $values): ?array
    {
        $date = Date::load(now());
        $document = $this->find($database, $id);
        $timestamps = [
            'created_at' => $document['created_at'] ?? $date->toArray(),
            'updated_at' => $date->toArray(),
            'deleted_at' => null,
        ];

        $values['_rev'] = $document['_rev'];
        $values = array_merge(
            $values,
            ['_rev' => $document['_rev']],
            $timestamps
        );

        $response = $this->request()->put($this->database($database, $id), $values);

        if ($response->json('ok')) {
            return array_merge(
                [
                    '_id' => $response->json('_id'),
                    '_rev' => $response->json('_rev'),
                ],
                $values,
                $timestamps
            );
        }

        return null;
    }

    public function delete(string $database, string $id): bool
    {
        $document = $this->find($database, $id);

        $response = $this->request()->delete($this->database($database, $id) . '?' . Arr::query(['rev' => $document['_rev'] ?? null]));

        return $response->json('ok', false);
    }

    public function softDelete(string $database, string $id): bool
    {
        $date = Date::load(now());
        $document = $this->find($database, $id);
        $timestamps = [
            'created_at' => $document['created_at'] ?? $date->toArray(),
            'updated_at' => $document['updated_at'] ?? $date->toArray(),
            'deleted_at' => $date->toArray(),
        ];

        $response = $this->request()->put(
            $this->database($database, $id),
            array_merge(
                $document,
                $timestamps,
            )
        );


        return $response->json('ok', false);
    }

    public function database(string $database, ?string $endpoint = null): string
    {
        $idLength = strlen((string) $endpoint);
        $count = (int) round($idLength / ($idLength + 1));

        $this->ensureDatabaseExists($database);

        $endpoint = collect(array_slice(
            [config('couchdb.url'), $database, $endpoint],
            0,
            2 + $count
        ))->implode('/');

        return $endpoint;
    }

    public function setIndexes(string $database, array $indexes)
    {
        if (empty($indexes)) return;

        try {
            $this->request()->post($this->database($database, '_index'), [
                'index' => [
                    'fields' => $indexes['fields'],
                ],
                'name' => $indexes['name']
            ]);
        } catch (Exception $e) {
            var_dump($e->getMessage());
        }
    }

    public function request(): PendingRequest
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Cookie' => $this->connection->get(),
        ]);
    }

    protected function getRows(Response $response, mixed $params): ?array
    {
        $key = 'rows';


        if (is_array($params)) {
            $key = 'docs';
        }

        return $response->json($key);
    }

    /**
     * Trying to force create database - it triggers error when db exists
     * @param string $database
     */
    private function ensureDatabaseExists(string $database): void
    {
        try {
            $this->request()->put(sprintf('%s/%s', config('couchdb.url'), $database));
        } catch (Exception $e) {
            var_dump($e->getMessage());
        }
    }
}
