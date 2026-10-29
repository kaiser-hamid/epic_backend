<?php

namespace Modules\BloodBank\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\BloodBank\Http\Resources\ComponentTypeResource;
use Modules\BloodBank\Queries\ListComponentTypes;

final class ComponentTypeController
{
    public function __construct(private readonly ListComponentTypes $listComponentTypes) {}

    public function index(): AnonymousResourceCollection
    {
        return ComponentTypeResource::collection($this->listComponentTypes->get());
    }
}
