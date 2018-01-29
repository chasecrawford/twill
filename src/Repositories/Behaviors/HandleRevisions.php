<?php

namespace A17\CmsToolkit\Repositories\Behaviors;

use Auth;

trait HandleRevisions
{

    public function beforeSaveHandleRevisions($object, $fields)
    {
        $lastRevisionPayload = json_decode($object->revisions->first()->payload ?? "{}", true);

        if ($this->payloadChanged($fields, $lastRevisionPayload)) {
            $object->revisions()->create([
                'payload' => json_encode($fields),
                'user_id' => Auth::user()->id ?? null,
            ]);
        }

        return $fields;
    }

    public function preview($id, $fields)
    {
        $object = $this->model->findOrFail($id);

        return $this->hydrateObject($object, $fields);
    }

    protected function hydrateObject($object, $fields)
    {
        $fields = $this->prepareFieldsBeforeSave($object, $fields);

        $object->fill(array_except($fields, $this->getReservedFields()));

        $object = $this->hydrate($object, $fields);

        return $object;
    }

    public function previewForRevision($id, $revisionId)
    {
        $object = $this->model->findOrFail($id);

        $fields = json_decode($object->revisions->where('id', $revisionId)->first()->payload, true);

        return $this->hydrateObject($object, $fields);
    }

    private function payloadChanged($requestPayload, $revisionPayload)
    {
        $requestPayloadValues = array_values($requestPayload);
        $revisionPayloadValues = array_values($revisionPayload);

        return array_sort_recursive($requestPayloadValues) !== array_sort_recursive($revisionPayloadValues);
    }

    public function hydrateMultiSelect($object, $fields, $relationship, $model = null)
    {
        $fieldsHasElements = isset($fields[$relationship]) && !empty($fields[$relationship]);
        $relatedElements = $fieldsHasElements ? $fields[$relationship] : [];

        $relationRepository = $this->getModelRepository($relationship, $model);
        $relatedElementsCollection = collect();

        foreach ($relatedElements as $relatedElement) {
            $newRelatedElement = $relationRepository->getById($relatedElement);
            $relatedElementsCollection->push($newRelatedElement);
        }

        $object->setRelation($relationship, $relatedElementsCollection);
    }

    public function hydrateBrowser($object, $fields, $relationship, $positionAttribute = 'position', $model = null)
    {
        return $this->hydrateOrderedBelongsTomany($object, $fields, $relationship, $positionAttribute, $model);
    }

    public function hydrateOrderedBelongsTomany($object, $fields, $relationship, $positionAttribute = 'position', $model = null)
    {
        $fieldsHasElements = isset($fields['browsers'][$relationship]) && !empty($fields['browsers'][$relationship]);
        $relatedElements = $fieldsHasElements ? $fields['browsers'][$relationship] : [];

        $relationRepository = $this->getModelRepository($relationship, $model);
        $relatedElementsCollection = collect();
        $position = 1;

        foreach ($relatedElements as $relatedElement) {
            $newRelatedElement = $relationRepository->getById($relatedElement['id']);
            $pivot = $newRelatedElement->newPivot($object, [$positionAttribute => $position++], $object->$relationship()->getTable(), true);
            $newRelatedElement->setRelation('pivot', $pivot);
            $relatedElementsCollection->push($newRelatedElement);
        }

        $object->setRelation($relationship, $relatedElementsCollection);
    }

    public function hydrateRepeater($object, $fields, $relationship, $model = null)
    {
        throw new \Exception('Hydrate repeater function is not implemented.');
    }

    public function getCountForMine()
    {
        return $this->model->where($this->countScope)->mine()->count();
    }

    public function getCountByStatusSlugHandleRevisions($slug)
    {
        if ($slug === 'mine') {
            return $this->getCountForMine();
        }

        return false;
    }
}
