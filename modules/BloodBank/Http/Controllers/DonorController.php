<?php

namespace Modules\BloodBank\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\BloodBank\Http\Resources\DonorDetailResource;
use Modules\BloodBank\Http\Resources\DonorResource;
use Modules\BloodBank\Models\Donor;
use Modules\BloodBank\Queries\ListDonors;
use Modules\BloodBank\Queries\LookupDonors;
use Modules\BloodBank\Queries\ShowDonor;
use Modules\Shared\Support\Data\PhoneLookupData;

final class DonorController
{
    public function __construct(
        private readonly ListDonors $listDonors,
        private readonly LookupDonors $lookupDonors,
        private readonly ShowDonor $showDonor,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return DonorResource::collection($this->listDonors->get($request));
    }

    public function lookup(PhoneLookupData $data): AnonymousResourceCollection
    {
        return DonorResource::collection($this->lookupDonors->get($data));
    }

    public function show(Donor $donor): DonorDetailResource
    {
        return DonorDetailResource::make($this->showDonor->get($donor));
    }
}
