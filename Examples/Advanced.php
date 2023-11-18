<?php

use App\Models\Department;
use App\Models\User;
use Tarre\HttpQueryBuilder\HttpQueryBuilder;
use Laravel\Scout\Builder as ScoutBuilder;
use Tarre\RedisScoutEngine\Callback;

// Query
class AdvancedQuery extends HttpQueryBuilder
{
    protected Department $department;

    public function base()
    {
        return User::class; // Assumes we have Scout serchable trait
    }

    public function withDepartment(Department $department): self
    {
        $this->department = $department;
        return $this;
    }

    public function withScoutBuilder(ScoutBuilder $builder): ScoutBuilder
    {
        // Modify scout builder if we should sort
        if($this->shouldSort()){
            return $builder
                ->orderBy($this->sortCol, $this->sortDirection)
                ->where('campaign_id', $this->department->id);
        }

        // Otherwise use the default behaviour
        return $builder->where('campaign_id', $this->department->id);
    }

    public function mapper(): ?Closure
    {
        // Because we are using a custom Scout driver, we use that callback to map results
        return function (Callback $cb) {
            return $cb->mapResult(
                fn(User $user) => new UserDto($user)
            );
        };
    }


}


// Controller

class AdvancedController
{

    public function index(Department $department, AdvancedQuery $query)
    {
        return $query
            ->withDepartment($department)
            ->sort()->search()->paginate();
    }

}
