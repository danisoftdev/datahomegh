<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;

final class OrderCartItemsValidator
{
    /**
     * @return array{ok: true, lines: list<array{network: string, phone_number: string, bundle_package_id: int, afa_registration?: array<string, mixed>}>}|array{ok: false, errors: MessageBag}
     */
    public static function validate(Request $request, User $user, Collection $catalog): array
    {
        $base = Validator::make($request->all(), [
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.network' => ['required', Rule::in(['MTN', 'MTN_AFA', 'Telecel', 'AirtelTigo'])],
            'items.*.phone_number' => ['required', 'string', 'regex:/^0[235]\d{8}$/'],
            'items.*.bundle_package_id' => ['required', 'integer', 'exists:bundle_packages,id'],
            'confirm' => ['accepted'],
        ]);

        if ($base->fails()) {
            return ['ok' => false, 'errors' => $base->errors()];
        }

        /** @var array{items: list<array<string, mixed>>} $validated */
        $validated = $base->validated();
        $lines = [];

        foreach ($validated['items'] as $i => $item) {
            $bundle = $catalog->firstWhere('id', (int) $item['bundle_package_id']);

            if ($bundle === null) {
                return ['ok' => false, 'errors' => new MessageBag([
                    'items.'.$i.'.bundle_package_id' => [__('This bundle is not available for your account.')],
                ])];
            }

            $allowedNetworks = $bundle->isMtnAfaRegistration()
                ? ['MTN', 'MTN_AFA']
                : ['MTN', 'Telecel', 'AirtelTigo'];

            if (! in_array($item['network'], $allowedNetworks, true)) {
                return ['ok' => false, 'errors' => new MessageBag([
                    'items.'.$i.'.network' => [__('Invalid network for this bundle.')],
                ])];
            }

            if ($bundle->isMtnAfaRegistration()) {
                $prefix = 'items.'.$i.'.afa_registration';
                $afaValidator = Validator::make($request->all(), array_merge(
                    [$prefix => ['required', 'array']],
                    AfaRegistrationPayload::nestedRules($prefix)
                ));
                $afaValidator->setAttributeNames([
                    $prefix.'.name' => __('Name'),
                    $prefix.'.phone' => __('Number'),
                    $prefix.'.ghana_card_number' => __('Ghana Card number'),
                    $prefix.'.date_of_birth' => __('Date of birth'),
                    $prefix.'.occupation' => __('Occupation'),
                    $prefix.'.location' => __('Location'),
                ]);

                if ($afaValidator->fails()) {
                    return ['ok' => false, 'errors' => $afaValidator->errors()];
                }
            } elseif ($request->filled('items.'.$i.'.afa_registration')) {
                return ['ok' => false, 'errors' => new MessageBag([
                    'items.'.$i.'.afa_registration' => [__('Registration applies only to MTN AFA bundles.')],
                ])];
            }

            $networkForOrder = $item['network'] === 'MTN_AFA' ? 'MTN' : $item['network'];

            $line = [
                'network' => $networkForOrder,
                'phone_number' => $item['phone_number'],
                'bundle_package_id' => (int) $item['bundle_package_id'],
            ];

            if ($bundle->isMtnAfaRegistration()) {
                $line['afa_registration'] = $request->input('items.'.$i.'.afa_registration');
            }

            $lines[] = $line;
        }

        return ['ok' => true, 'lines' => $lines];
    }
}
