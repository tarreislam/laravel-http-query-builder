<?php

namespace Tarre\HttpQueryBuilder;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Laravel\Scout\Builder as ScoutBuilder;
use Tarre\HttpQueryBuilder\Exceptions\NotSupportedException;
use Tarre\HttpQueryBuilder\Exceptions\ParseBaseException;


abstract class HttpQueryBuilder
{
    protected Request $request;
    protected string $sortCol;
    protected string $sortDirection;
    public int $resultsPerPage = 15;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function sort(string $sortColKey = 'sortCol', string $sortDirectionKey = 'sortDirection'): self
    {
        $sortCol = $this->request->get($sortColKey);
        $sortDirection = $this->request->get($sortDirectionKey);

        $this->sortCol = $sortCol ?: '';
        $this->sortDirection = $sortDirection ?: '';

        return $this;
    }

    public function shouldSort(): bool
    {
        return !empty($this->sortCol) && !empty($this->sortDirection);
    }

    public function canSort(): bool
    {
        return $this->shouldSort();
    }

    /**
     * Class FQDN or base for query.
     * @return mixed
     */
    public abstract function base();

    /**
     * @param $request
     * @return $this
     */
    public function withCustomRequest(Request $request): self
    {
        $this->request = $request;
        return $this;
    }

    public function withScoutBuilder(ScoutBuilder $builder): ScoutBuilder
    {
        return $builder;
    }

    public function mapper(): ?Closure
    {
        return null;
    }

    public function wrapper(): ?Closure
    {
        return null;
    }

    /**
     * @param false $forPagination
     * @return Model|QueryBuilder|SupportCollection
     * @throws \Exception
     */
    public final function get($forPagination = false)
    {
        $model = $this->model();
        /*
         * Figure out how to get all results
         */
        if ($model instanceof Model || $model instanceof QueryBuilder || $model instanceof Relation || $model instanceof EloquentBuilder) {
            if ($forPagination) {
                return $model;
            }
            $items = $model->get();
        } elseif ($model instanceof SupportCollection) {
            $items = $model;
        } else {
            throw new ParseBaseException('Could not figure out how to retrieve results');
        }
        /*
         * Map records in result
         */
        if (!is_null($mapper = $this->mapper())) {
            $items = $items->map($mapper);
        }
        /*
         * Wrap entire result
         */
        if (!is_null($wrapper = $this->wrapper())) {
            $items = $wrapper($items);
        }
        /*
         * Return collection
         */
        return $items;
    }

    /**
     * @return ScoutBuilder|null
     */
    public final function search(string $key = 'search'): ?ScoutBuilder
    {
        $mdl = $this->base();
        $search = $this->request->get($key);
        return $this->withScoutBuilder($mdl::search($search, $this->mapper()));
    }

    /**
     * @return LengthAwarePaginator
     * @throws \Exception
     */
    public final function paginate(string $pageKey = 'page', string $ppKey = 'perPage', string $pathKey = 'path'): LengthAwarePaginator
    {
        $perPage = $this->request->get($ppKey, $this->resultsPerPage);
        /*
         * Grab base model
         */
        $items = $this->get(true);
        /*
         * Models and query builders asking for pagination, we serve them via the models,
         */
        if ($items instanceof QueryBuilder || $items instanceof Model || $items instanceof Relation || $items instanceof EloquentBuilder) {
            /*
            * Map result
            */
            if (!is_null($this->mapper())) {
                throw new NotSupportedException("You cannot map results when paginating from raw builder, please use ::get() or ::all() and modify the data in the \"base\" method");
            }
            return $items->paginate();
        }
        /*
         * Get pages
         */
        $page = $this->request->get($pageKey);
        /*
         * Paginate
         */
        $items = $items->forPage($page, $perPage);
        return new LengthAwarePaginator($items, $items->count(), $perPage, $page, [
            $pathKey => $this->request->url(),
        ]);

    }

    protected function model()
    {
        $model = $this->base();
        if (is_string($model) && class_exists($model)) {
            $model = new $model;
        }
        return $model;
    }

}
