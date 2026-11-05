<?php

namespace App\Modules\Epi\Application\ConfirmOffer;

use App\Models\Epi\EpiApplicationPartner;
use App\Modules\Epi\Application\Flow\Universal\IL_PDL\Step;
use App\Services\Notification\NotificationService;
use App\Services\Sms\SmsService;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class ConfirmOfferAction
{
    public function __construct(
        private readonly ContractPdfBuilder $pdfBuilder,
    ) {
    }

    public function handle(string $uid, array $data): array
    {
        $application = EpiApplicationPartner::query()
            ->where('uid', $uid)
            ->first();

        if ($application === null) {
            throw new \Exception('Application not found');
        }

        if ($application->step !== Step::CreditOffer->value) {
            throw new \Exception('Invalid application step');
        }

        $offer = (new OfferCalculator())->calculate(
            (float) $data['amount'] * 100,
            (int) $data['term'] * 1.1,
        );

        DB::beginTransaction();

        try {
            $application->update([
                'step'   => Step::WaitSigning->value,
                'amount' => $offer['amount'],
            ]);

            $response = $this->sendToPartner($application, $offer);

            if (($response['status'] ?? null) !== 'ok') {
                DB::rollBack();

                throw new \Exception('Partner rejected offer');
            }

            $application->update(['partner_status' => 'confirmed']);

            $pdf = $this->pdfBuilder->build($application, $offer);
            Storage::disk('s3')->put("contracts/{$application->uid}.pdf", $pdf);

            SmsService::send($application->client->phone, 'Подтвердите договор');
            NotificationService::send($application->client->email, 'Договор готов');

            DB::commit();

            return ['confirmed' => true, 'total' => $offer['total']];
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Confirm offer failed: ' . $e->getMessage());

            throw $e;
        }
    }

    private function sendToPartner(EpiApplicationPartner $application, array $offer): array
    {
        switch ($application->epiPartner->slug) {
            case 'partner_one':
                $url = config('partner_one.url') . '/offer/confirm';
                $headers = ['X-Partner-Token' => config('partner_one.token')];
                break;

            case 'partner_two':
                $url = config('partner_two.url') . '/v2/offers/confirm';
                $headers = ['Authorization' => 'Bearer ' . config('partner_two.token')];
                break;

            default:
                throw new \Exception('Unknown partner: ' . $application->epiPartner->slug);
        }

        $client = new Client(['timeout' => 5]);

        $response = $client->post($url, [
            'json' => [
                'offerId' => $application->external_id,
                'amount'  => $offer['amount'],
                'term'    => $offer['term'],
                'total'   => $offer['total'],
            ],
            'headers' => $headers,
        ]);

        return json_decode($response->getBody()->getContents(), true) ?? [];
    }
}