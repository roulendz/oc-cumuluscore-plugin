<?php namespace  Initbiz\CumulusCore\Repositories;

use Initbiz\CumulusCore\Contracts\UserInterface;

class UserRepository implements UserInterface
{
    public $userModel;
    public $groupModel;

    public function __construct()
    {
        $this->userModel = new \Rainlab\User\Models\User;
        $this->groupModel = new \Rainlab\User\Models\UserGroup;
    }

    public function all($columns = array('*'))
    {
        return $this->userModel->get($columns);
    }

    public function paginate(int $perPage = 15, $columns = array('*'))
    {
        return $this->userModel->paginate($perPage, $columns);
    }

    public function create(array $data)
    {
        return $this->userModel->create($data);
    }

    public function update(array $data, $id, $attribute="id")
    {
        $user = $this->userModel->where($attribute, '=', $id)->first();
        foreach ($data as $key => $value) {
            $user->$key = $value;
        }
        $user->save();
    }

    public function delete(int $id)
    {
        return $this->userModel->destroy($id);
    }

    public function find(int $id, $columns = array('*'))
    {
        return $this->userModel->find($id, $columns);
    }

    public function findBy(string $field, $value, $columns = array('*'))
    {
        return $this->userModel->where($field, '=', $value)->first($columns);
    }

    public function getUserClusterList(int $userId)
    {
        return $this->userModel
                    ->find($userId)
                    ->clusters()
                    ->get();
    }

    public function getActivatedUsers($columns = array('*'))
    {
        return $this->userModel->where("is_activated", true)->get($columns);
    }

    public function getByRelationPropertiesArray(string $relationName, string $propertyName, array $array)
    {
        return $this->userModel->whereHas($relationName, function ($query) use ($propertyName, $array) {
            $query->whereIn($propertyName, $array);
        })->get();
    }

    public function getUsingArray(string $field, array $array)
    {
        $users = $this->userModel->where($field, array_shift($array));
        foreach ($array as $item) {
            $users = $users->orWhere($field, $item);
        }
        return $users->get();
    }

    public function addUserToGroup($userId, $groupCode)
    {
        $group = $this->groupModel->where('code', $groupCode)->first() ?: false;
        if ($group) {
            $user = $this->userModel->find($userId);
            $user->groups()->add($group);
        }
    }

    public function activateUser($userId)
    {
        $user = $this->find($userId);
        $user->attemptActivation($user->activation_code);
    }
}
