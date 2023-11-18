<?php

use Illuminate\Database\Eloquent\Collection as DBCollection;
use Tarre\HttpQueryBuilder\HttpQueryBuilder;
use Laravel\Scout\Builder as ScoutBuilder;
use App\Models\User;

// Query
class BasicQuery extends HttpQueryBuilder
{

    public function base()
    {
        if ($this->shouldSort()) {
            return User::orderBy($this->sortCol, $this->sortDirection);
        }
        return User::class;
    }

    /*
     * Map each result
     */
    public function mapper(): ?Closure
    {
        return function (User $user) {
            return new UserDto($user);
        };
    }

    /*
     * Wrap the entire result
     */
    public function wrapper(): ?Closure
    {
        return function (DBCollection $collection) {
            return UserDto::collect($collection);
        };
    }
}


// Controller

class BasicController
{

    public function index(BasicQuery $query)
    {
        return $query->sort()->paginate();
    }

}
