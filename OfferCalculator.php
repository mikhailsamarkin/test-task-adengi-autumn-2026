<?php

namespace App\Modules\Epi\Application\ConfirmOffer;

final class OfferCalculator
{
    public function calculate(float $amount, int $term): array
    {
        $monthly = $amount * 100 / $term * 1.1;
        $total = $monthly * $term;

        return [
            'amount'  => $amount * 100,
            'term'    => $term,
            'monthly' => round($monthly, 2),
            'total'   => round($total, 2),
        ];
    }
}