<?php namespace Initbiz\CumulusCore\Repositories;

use Lang;
use Event;
use Validator;
use October\Rain\Exception\ValidationException;
use Initbiz\CumulusCore\Contracts\ClusterInterface;
use Initbiz\CumulusCore\Repositories\ClusterFeatureLogRepository;

class ClusterRepository implements ClusterInterface
{
    public $clusterModel;

    public $userRepository;

    public $currentCluster;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $currentClusterSlug = '')
    {
        $this->clusterModel = new \Initbiz\CumulusCore\Models\Cluster;
        $this->userRepository = new UserRepository();
        if ($currentClusterSlug !== '') {
            $this->refreshCurrentCluster($currentClusterSlug);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function all($columns = array('*'))
    {
        return $this->clusterModel->get($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(int $perPage = 15, $columns = array('*'))
    {
        return $this->clusterModel->paginate($perPage, $columns);
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data)
    {
        return $this->clusterModel->create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function update(array $data, $id, $attribute="id")
    {
        $cluster = $this->clusterModel->where($attribute, '=', $id)->first();
        foreach ($data as $key => $value) {
            $cluster->$key = $value;
        }
        $cluster->save();
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id)
    {
        return $this->clusterModel->destroy($id);
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $id, $columns = array('*'))
    {
        return $this->clusterModel->find($id, $columns);
    }

    /**
     * {@inheritdoc}
     */
    public function findBy(string $field, $value, $columns = array('*'))
    {
        return $this->clusterModel->where($field, '=', $value)->first($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function getByRelationPropertiesArray(string $relationName, string $propertyName, array $array)
    {
        return $this->clusterModel->whereHas($relationName, function ($query) use ($propertyName, $array) {
            $query->whereIn($propertyName, $array);
        })->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getUsingArray(string $field, array $array)
    {
        $clusters = $this->clusterModel->where($field, array_shift($array));
        foreach ($array as $item) {
            $clusters = $clusters->orWhere($field, $item);
        }
        return $clusters->get();
    }

    /**
     * {@inheritdoc}
     */
    public function canEnterCluster(int $userId, string $clusterSlug)
    {
        return $this->userRepository->find($userId)->clusters()->whereSlug($clusterSlug)->first()? true : false;
    }

    /**
     * {@inheritdoc}
     */
    public function canEnterFeature(string $clusterSlug, string $featureCode)
    {
        $this->refreshCurrentCluster($clusterSlug);

        $clusterFeatures = $this->getClusterFeatures($clusterSlug);

        $can = in_array($featureCode, $clusterFeatures) ? true : false;

        return $can;
    }

    /**
     * {@inheritdoc}
     */
    public function getClustersUsers(array $clustersSlugs)
    {
        $users = '';

        $clustersIds = $this->getUsingArray('slug', $clustersSlugs)->pluck('id')->toArray();

        $users = $this->userRepository->getByRelationPropertiesArray('clusters', 'initbiz_cumuluscore_clusters.id', $clustersIds);

        return $users;
    }

    /**
     * {@inheritdoc}
     */
    public function getClusterFeatures(string $clusterSlug):array
    {
        $this->refreshCurrentCluster($clusterSlug);

        $clusterFeatures = $this->currentCluster->plan()->first()->features;

        if (!isset($clusterFeatures) || $clusterFeatures === "0") {
            $clusterFeatures = [];
        }

        $clusterFeatures = (array) $clusterFeatures;
        return $clusterFeatures;
    }

    /**
     * {@inheritdoc}
     */
    public function addUserToCluster(int $userId, string $clusterSlug)
    {
        $this->refreshCurrentCluster($clusterSlug);
        if ($this->currentCluster) {
            $user = $this->userRepository->find($userId);

            Event::fire('initbiz.cumuluscore.addUserToCluster', [$user, $this->currentCluster]);

            $user->clusters()->syncWithoutDetaching($this->currentCluster);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addClusterToPlan(string $clusterSlug, string $planSlug)
    {
        $this->planRepository = new PlanRepository();

        $plan = $this->planRepository->findBy('slug', $planSlug);
        if ($plan) {
            $this->refreshCurrentCluster($clusterSlug);

            $this->currentCluster->plan()->associate($plan);
            $this->currentCluster->save();

            Event::fire('initbiz.cumuluscore.addClusterToPlan', [$this->currentCluster, $plan]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getClustersPlans(array $clustersSlugs)
    {
        $plans = [];
        $clusters = $this->getUsingArray('slug', $clustersSlugs);

        foreach ($clusters as $cluster) {
            $plans[] = $cluster->plan()->first();
        }

        return collect($plans);
    }

    /**
     * {@inheritdoc}
     */
    public function refreshCurrentCluster(string $clusterSlug)
    {
        //It's good place to enable caching fo clusters
        if (!isset($this->currentCluster) || $this->currentCluster->slug !== $clusterSlug) {
            $this->currentCluster = $this->findBy('slug', $clusterSlug);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentCluster()
    {
        if (!$this->currentCluster) {
            return [];
        }

        $currentCluster = $this->currentCluster->toArray();
        if ($logo = $this->currentCluster->logo()->first()) {
            $currentCluster['logo'] = $logo->toArray();
        }

        return $currentCluster;
    }

    /**
     * {@inheritdoc}
     */
    public function usernameUnique(string $username, string $clusterSlug)
    {
        $this->refreshCurrentCluster($clusterSlug);

        $rules = [
            'username' => 'required|between:4,255|alpha_dash|unique:initbiz_cumuluscore_clusters,username,' . $this->currentCluster->id,
        ];

        $data = [
            'username' => $username,
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return false;
        }

        $state = Event::fire('initbiz.cumuluscore.usernameUnique', [$username, $clusterSlug], true);

        if ($state === false) {
            return false;
        }

        return true;
    }
}
