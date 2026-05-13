<?php

declare(strict_types=1);

/**
 * DataHomeGH REST API v1 — pure PHP (no framework).
 */
define('API_V1_ROOT', __DIR__);
define('PROJECT_ROOT', dirname(__DIR__, 3));

require API_V1_ROOT.'/config/bootstrap.php';

require_once API_V1_ROOT.'/services/Support.php';
require_once API_V1_ROOT.'/controllers/AuthApi.php';
require_once API_V1_ROOT.'/controllers/WalletApi.php';
require_once API_V1_ROOT.'/controllers/BundleApi.php';
require_once API_V1_ROOT.'/controllers/OrderApi.php';
require_once API_V1_ROOT.'/controllers/AdminApi.php';

api_handle_options_preflight();
api_cors_headers();

$pdo = api_db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = api_route_path();

api_dispatch($pdo, $method, $path);

function api_route_path(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url((string) $uri, PHP_URL_PATH) ?: '/';
    $prefix = '/api/v1';

    if (str_starts_with($path, $prefix)) {
        $path = substr($path, strlen($prefix));
    }

    return trim($path, '/') ?: '';
}

/**
 * @param  array<string, array<string, callable(PDO):void>>  $routes
 */
function api_dispatch(PDO $pdo, string $method, string $path): void
{
    $routes = api_route_definitions();

    $methodRoutes = $routes[$method] ?? [];
    foreach ($methodRoutes as $pattern => $handler) {
        if (preg_match($pattern, $path, $matches)) {
            $args = array_slice($matches, 1);
            $handler($pdo, ...$args);

            return;
        }
    }

    Response::error('Not found', 404);
}

/**
 * @return array<string, array<string, callable>>
 */
function api_route_definitions(): array
{
    return [
        'POST' => [
            '#^auth/login$#' => fn (PDO $pdo) => AuthApi::login($pdo),
            '#^auth/register$#' => fn (PDO $pdo) => AuthApi::register($pdo),
            '#^auth/logout$#' => fn (PDO $pdo) => AuthApi::logout($pdo),
            '#^auth/password-reset-request$#' => fn (PDO $pdo) => AuthApi::passwordResetRequest($pdo),
            '#^auth/password-reset$#' => fn (PDO $pdo) => AuthApi::passwordReset($pdo),
            '#^wallet/topup/initialize$#' => fn (PDO $pdo) => WalletApi::initTopup($pdo),
            '#^bundles$#' => fn (PDO $pdo) => BundleApi::store($pdo),
            '#^orders$#' => fn (PDO $pdo) => OrderApi::store($pdo),
            '#^orders/(\d+)/note$#' => fn (PDO $pdo, string $id) => OrderApi::addNote($pdo, (int) $id),
            '#^admin/users/(\d+)/approve$#' => fn (PDO $pdo, string $id) => AdminApi::approveUser($pdo, (int) $id),
            '#^admin/users/(\d+)/freeze$#' => fn (PDO $pdo, string $id) => AdminApi::freezeUser($pdo, (int) $id),
            '#^admin/wallet/credit$#' => fn (PDO $pdo) => AdminApi::walletCredit($pdo),
            '#^admin/wallet/debit$#' => fn (PDO $pdo) => AdminApi::walletDebit($pdo),
            '#^admin/notifications$#' => fn (PDO $pdo) => AdminApi::notificationsBroadcast($pdo),
            '#^admin/roles$#' => fn (PDO $pdo) => AdminApi::rolesStore($pdo),
        ],
        'GET' => [
            '#^wallet$#' => fn (PDO $pdo) => WalletApi::balance($pdo),
            '#^wallet/ledger$#' => fn (PDO $pdo) => WalletApi::ledger($pdo),
            '#^wallet/topup/verify/([^/]+)$#' => fn (PDO $pdo, string $ref) => WalletApi::verifyTopup($pdo, $ref),
            '#^bundles$#' => fn (PDO $pdo) => BundleApi::index($pdo),
            '#^orders$#' => fn (PDO $pdo) => OrderApi::index($pdo),
            '#^orders/all$#' => fn (PDO $pdo) => OrderApi::indexAll($pdo),
            '#^orders/(\d+)$#' => fn (PDO $pdo, string $id) => OrderApi::show($pdo, (int) $id),
            '#^admin/users$#' => fn (PDO $pdo) => AdminApi::usersIndex($pdo),
            '#^admin/roles$#' => fn (PDO $pdo) => AdminApi::rolesIndex($pdo),
        ],
        'PUT' => [
            '#^bundles/(\d+)$#' => fn (PDO $pdo, string $id) => BundleApi::update($pdo, (int) $id),
            '#^admin/roles/(\d+)$#' => fn (PDO $pdo, string $id) => AdminApi::rolesUpdate($pdo, (int) $id),
        ],
        'PATCH' => [
            '#^orders/(\d+)/status$#' => fn (PDO $pdo, string $id) => OrderApi::patchStatus($pdo, (int) $id),
        ],
        'DELETE' => [
            '#^bundles/(\d+)$#' => fn (PDO $pdo, string $id) => BundleApi::destroy($pdo, (int) $id),
            '#^admin/users/(\d+)$#' => fn (PDO $pdo, string $id) => AdminApi::destroyUser($pdo, (int) $id),
        ],
    ];
}
