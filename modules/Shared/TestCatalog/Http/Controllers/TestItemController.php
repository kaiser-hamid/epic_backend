<?php

namespace Modules\Shared\TestCatalog\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Shared\TestCatalog\Data\LookupTestItemsData;
use Modules\Shared\TestCatalog\Http\Resources\TestItemResource;
use Modules\Shared\TestCatalog\Queries\LookupTestItems;

final class TestItemController
{
    public function __construct(private readonly LookupTestItems $lookupTestItems) {}

    public function lookup(LookupTestItemsData $data): AnonymousResourceCollection
    {
        return TestItemResource::collection($this->lookupTestItems->get($data));
    }
}
