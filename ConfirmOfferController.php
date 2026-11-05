<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Epi;

use App\Http\Requests\Api\V1\Epi\ConfirmOfferRequest;
use App\Modules\Epi\Application\ConfirmOffer\ConfirmOfferAction;
use Illuminate\Http\JsonResponse;

final class ConfirmOfferController extends Controller
{
    public function __construct(
        private readonly ConfirmOfferAction $confirmOffer,
    ) {
    }

    public function __invoke(ConfirmOfferRequest $request, string $applicationUid): JsonResponse
    {
        $result = $this->confirmOffer->handle($applicationUid, $request->validated());

        return response()->json(['data' => $result]);
    }
}