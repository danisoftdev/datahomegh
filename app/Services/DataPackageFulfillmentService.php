<?php

namespace App\Services;

use App\Models\BundlePackage;
use App\Models\FulfillmentApiProfile;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\Fulfillment\EncartaFulfillmentClient;
use App\Services\Fulfillment\GeonetFulfillmentClient;
use App\Services\Fulfillment\IgetFulfillmentClient;
use App\Support\EncartaMtnNetwork;
use App\Support\FulfillmentProviderType;
use App\Support\GeonetMtnNetworkKey;
use App\Support\IgetTelecelBundleType;

final class DataPackageFulfillmentService
{
    public function __construct(
        private readonly IgetFulfillmentClient $igetClient,
        private readonly GeonetFulfillmentClient $geonetClient,
        private readonly EncartaFulfillmentClient $encartaClient,
    ) {}

    /**
     * @return array{ok: bool, message: string, skipped?: bool}
     */
    public function dispatchAfterOrderPlaced(Order $order): array
    {
        $order->loadMissing(['bundlePackage', 'user.role']);

        if (! $this->shouldAutoDispatchToProvider($order)) {
            return [
                'ok' => false,
                'message' => __('This order is not eligible for automatic provider dispatch.'),
                'skipped' => true,
            ];
        }

        $bundle = $order->bundlePackage;
        if ($bundle === null || $bundle->isMtnAfaRegistration()) {
            return ['ok' => false, 'message' => __('MTN AFA orders are not sent to external APIs.'), 'skipped' => true];
        }

        $profile = FulfillmentApiProfile::activeForNetwork((string) $order->network);
        if ($profile === null) {
            $msg = __('No active API profile for network :network. Go to Data APIs and activate one.', ['network' => $order->network]);
            $this->recordDispatchError($order->id, $msg);

            return ['ok' => false, 'message' => $msg, 'skipped' => true];
        }

        if (! FulfillmentProviderType::allowsNetwork($profile->provider_type, (string) $order->network)) {
            $msg = __('The active API profile provider does not match network :network.', ['network' => $order->network]);
            $this->recordDispatchError($order->id, $msg);

            return ['ok' => false, 'message' => $msg, 'skipped' => true];
        }

        $productCode = $this->resolveProductCode($order, $bundle, $profile);
        if ($productCode === '') {
            $msg = $this->missingProductCodeMessage($profile);
            $this->recordDispatchError($order->id, $msg);

            return ['ok' => false, 'message' => $msg, 'skipped' => true];
        }

        $result = match ($profile->provider_type) {
            FulfillmentProviderType::IGET => $this->igetClient->place($order, $bundle, $profile, $productCode),
            FulfillmentProviderType::GEONET => $this->geonetClient->place($order, $bundle, $profile, $productCode),
            FulfillmentProviderType::ENCARTA => $this->encartaClient->place($order, $bundle, $profile, $productCode),
            default => ['ok' => false, 'message' => __('Unknown API provider type.')],
        };

        if (! $result['ok']) {
            $this->recordDispatchError($order->id, $result['message']);

            return $result;
        }

        Order::query()->whereKey($order->id)->update([
            'provider_order_reference' => $result['reference'] ?? null,
            'fulfillment_api_profile_id' => $profile->id,
            'provider_status' => $result['status'] ?? null,
            'provider_status_synced_at' => now(),
            'provider_dispatch_error' => null,
        ]);

        return $result;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function refreshProviderStatus(Order $order): array
    {
        $order->loadMissing('fulfillmentApiProfile');

        $profile = $order->fulfillmentApiProfile;
        $ref = trim((string) ($order->provider_order_reference ?? ''));

        if ($profile === null || $ref === '') {
            return ['ok' => false, 'message' => __('This order has no linked provider reference or API profile.')];
        }

        $result = match ($profile->provider_type) {
            FulfillmentProviderType::IGET => $this->igetClient->refreshStatus($order, $profile, $ref),
            FulfillmentProviderType::GEONET => $this->geonetClient->refreshStatus($order, $profile, $ref),
            FulfillmentProviderType::ENCARTA => $this->encartaClient->refreshStatus($order, $profile, $ref),
            default => ['ok' => false, 'message' => __('Unknown API provider type.')],
        };

        if (! $result['ok']) {
            return $result;
        }

        Order::query()->whereKey($order->id)->update([
            'provider_status' => $result['status'] ?? null,
            'provider_status_synced_at' => now(),
        ]);

        return $result;
    }

    /**
     * Auto API: buyer/agent system roles and custom roles (e.g. dealer) with a checkout persona.
     */
    private function shouldAutoDispatchToProvider(Order $order): bool
    {
        $user = $order->user;
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSupplier()) {
            return false;
        }

        if ($user->isAgent() || $user->isBuyer()) {
            return true;
        }

        $user->loadMissing('role');

        return $user->role !== null
            && ($user->role->usesAgentPersona() || $user->role->usesBuyerPersona());
    }

    /**
     * bundleType (iGet Telecel) or network_key (Geonettech MTN — fixed in config).
     */
    private function resolveProductCode(Order $order, BundlePackage $bundle, FulfillmentApiProfile $profile): string
    {
        if ($profile->provider_type === FulfillmentProviderType::GEONET
            && GeonetMtnNetworkKey::isMtnNetwork((string) $order->network)) {
            return GeonetMtnNetworkKey::resolve();
        }

        if ($profile->provider_type === FulfillmentProviderType::ENCARTA
            && EncartaMtnNetwork::isMtnNetwork((string) $order->network)) {
            return EncartaMtnNetwork::resolve();
        }

        if ($profile->provider_type === FulfillmentProviderType::IGET
            && IgetTelecelBundleType::isTelecelNetwork((string) $order->network)) {
            $resolved = IgetTelecelBundleType::resolve();

            return $resolved !== '' ? $resolved : '';
        }

        $fromBundle = trim((string) ($bundle->provider_bundle_type ?? ''));
        if ($fromBundle !== '') {
            return $fromBundle;
        }

        $fromProfile = trim((string) ($profile->default_provider_bundle_type ?? ''));
        if ($fromProfile !== '') {
            return $fromProfile;
        }

        return trim((string) config(
            'datahome.fulfillment.fallback_codes.'.$profile->provider_type.'.'.strtoupper((string) $order->network),
            ''
        ));
    }

    private function missingProductCodeMessage(FulfillmentApiProfile $profile): string
    {
        return match ($profile->provider_type) {
            FulfillmentProviderType::GEONET => __('Geonettech MTN network_key is not configured. Set FULFILLMENT_GEONET_MTN_NETWORK_KEY in .env (default YELLO).'),
            FulfillmentProviderType::ENCARTA => __('Encarta MTN networkKey is not configured. Set FULFILLMENT_ENCARTA_MTN_NETWORK_KEY in .env (default YELLO).'),
            FulfillmentProviderType::IGET => __('iGet Telecel bundleType is not configured. Set FULFILLMENT_IGET_TELECEL_BUNDLE_TYPE in .env (default Telecel-5959).'),
            default => __('No provider product code configured for this order.'),
        };
    }

    private function recordDispatchError(int $orderId, string $message): void
    {
        Order::query()->whereKey($orderId)->update([
            'provider_dispatch_error' => $message,
        ]);
    }
}
