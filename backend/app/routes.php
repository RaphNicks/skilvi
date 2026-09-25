<?php
declare(strict_types=1);

use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\AccountController;
use App\Controllers\DiscoveryController;
use App\Controllers\JobController;
use App\Controllers\OrderController;
use App\Controllers\PaymentController;

Router::get('/login', [AuthController::class, 'loginPage']);
Router::get('/login.html', [AuthController::class, 'loginPage']);
Router::get('/logout', [AuthController::class, 'logoutPage']);
Router::get('/logout.html', [AuthController::class, 'logoutPage']);
Router::get('/forgot-password', [AuthController::class, 'forgotPage']);
Router::get('/forgot-password.html', [AuthController::class, 'forgotPage']);

Router::get('/api/geo/countries', [\App\Controllers\GeoController::class, 'countries']);
Router::get('/api/geo/states', [\App\Controllers\GeoController::class, 'states']);
Router::get('/api/geo/cities', [\App\Controllers\GeoController::class, 'cities']);

Router::get('/api/health', [AuthController::class, 'health']);
Router::get('/api/csrf', [AuthController::class, 'csrf']);
Router::get('/api/me', [AuthController::class, 'me']);

Router::post('/api/auth/register', [AuthController::class, 'register']);
Router::post('/api/auth/login', [AuthController::class, 'login']);
Router::post('/api/auth/verify', [AuthController::class, 'verify']);
Router::post('/api/auth/resend', [AuthController::class, 'resend']);
Router::post('/api/auth/logout', [AuthController::class, 'logout']);
Router::post('/api/auth/password/forgot', [AuthController::class, 'forgot']);
Router::post('/api/auth/password/reset', [AuthController::class, 'reset']);

Router::patch('/api/me', [AccountController::class, 'update']);
Router::post('/api/me', [AccountController::class, 'update']);
Router::post('/api/me/password', [AccountController::class, 'password']);
Router::post('/api/me/email', [AccountController::class, 'email']);
Router::post('/api/me/phone', [AccountController::class, 'phone']);
Router::get('/api/me/export', [AccountController::class, 'export']);
Router::get('/api/me/consent', [AccountController::class, 'consent']);
Router::post('/api/me/delete-request', [AccountController::class, 'requestDeletion']);
Router::post('/api/me/deactivate', [AccountController::class, 'deactivate']);
Router::post('/api/me/avatar', [AccountController::class, 'avatar']);
Router::get('/api/files/{token}', [AccountController::class, 'file']);

Router::get('/api/landing', [DiscoveryController::class, 'landing']);
Router::get('/api/jobs', [DiscoveryController::class, 'jobs']);
Router::get('/api/jobs/{id}', [DiscoveryController::class, 'job']);
Router::post('/api/jobs/{id}/propose', [DiscoveryController::class, 'propose']);
Router::get('/api/workers', [DiscoveryController::class, 'workers']);
Router::get('/api/workers/{id}', [DiscoveryController::class, 'worker']);
Router::get('/api/services/{id}', [DiscoveryController::class, 'service']);
Router::get('/api/categories', [DiscoveryController::class, 'categories']);
Router::get('/api/categories/{slug}', [DiscoveryController::class, 'category']);
Router::get('/api/saved', [DiscoveryController::class, 'saved']);
Router::post('/api/saved/{id}', [DiscoveryController::class, 'save']);
Router::delete('/api/saved/{id}', [DiscoveryController::class, 'unsave']);
Router::post('/api/saved/{id}/remove', [DiscoveryController::class, 'unsave']);

Router::post('/api/jobs', [JobController::class, 'create']);
Router::get('/api/me/jobs', [JobController::class, 'mine']);
Router::post('/api/jobs/{id}/close', [JobController::class, 'close']);
Router::post('/api/jobs/{id}/cancel', [JobController::class, 'cancel']);
Router::post('/api/proposals/{id}/shortlist', [JobController::class, 'shortlist']);
Router::post('/api/proposals/{id}/reject', [JobController::class, 'reject']);
Router::post('/api/proposals/{id}/accept', [JobController::class, 'accept']);
Router::post('/api/proposals/{id}/withdraw', [JobController::class, 'withdrawProposal']);

Router::get('/api/orders', [OrderController::class, 'list']);
Router::get('/api/orders/{id}', [OrderController::class, 'get']);
Router::post('/api/orders/{id}/start', [OrderController::class, 'start']);
Router::post('/api/orders/{id}/submit', [OrderController::class, 'submit']);
Router::post('/api/orders/{id}/approve', [OrderController::class, 'approve']);
Router::post('/api/orders/{id}/revision', [OrderController::class, 'revision']);
Router::post('/api/orders/{id}/cancel', [OrderController::class, 'cancel']);
Router::post('/api/orders/{id}/review', [OrderController::class, 'review']);
Router::post('/api/orders/{id}/dispute', [OrderController::class, 'dispute']);
Router::post('/api/orders/{id}/message', [\App\Controllers\CommsController::class, 'orderMessage']);

Router::get('/api/disputes', [\App\Controllers\CommsController::class, 'disputes']);
Router::get('/api/disputes/{id}', [\App\Controllers\CommsController::class, 'disputeGet']);
Router::post('/api/disputes/{id}/reply', [\App\Controllers\CommsController::class, 'disputeReply']);
Router::get('/api/admin/disputes', [\App\Controllers\CommsController::class, 'adminDisputes']);
Router::post('/api/admin/disputes/{id}/resolve', [\App\Controllers\CommsController::class, 'disputeResolve']);
Router::post('/api/admin/verifications/{id}/action', [\App\Controllers\CommsController::class, 'verificationReview']);

Router::get('/api/admin/dashboard', [\App\Controllers\AdminController::class, 'dashboard']);
Router::get('/api/admin/users', [\App\Controllers\AdminController::class, 'users']);
Router::get('/api/admin/users/{id}', [\App\Controllers\AdminController::class, 'userGet']);
Router::post('/api/admin/users/{id}/update', [\App\Controllers\AdminController::class, 'userUpdate']);
Router::patch('/api/admin/users/{id}', [\App\Controllers\AdminController::class, 'userAction']);
Router::post('/api/admin/users/{id}', [\App\Controllers\AdminController::class, 'userAction']);
Router::post('/api/admin/users/{id}/action', [\App\Controllers\AdminController::class, 'userAction']);
Router::post('/api/admin/jobs/{id}/action', [\App\Controllers\AdminController::class, 'jobAction']);
Router::post('/api/admin/services/{id}/action', [\App\Controllers\AdminController::class, 'serviceAction']);
Router::get('/api/admin/orders', [\App\Controllers\AdminController::class, 'orders']);
Router::post('/api/admin/orders/{id}/action', [\App\Controllers\AdminController::class, 'orderAction']);
Router::get('/api/admin/payments', [\App\Controllers\AdminController::class, 'payments']);
Router::get('/api/admin/verifications', [\App\Controllers\AdminController::class, 'verifications']);
Router::get('/api/admin/promotions', [\App\Controllers\AdminController::class, 'promotions']);
Router::post('/api/admin/promotions/{id}/pause', [\App\Controllers\AdminController::class, 'promoPause']);
Router::get('/api/admin/categories', [\App\Controllers\AdminController::class, 'categories']);
Router::post('/api/admin/categories/{id}/action', [\App\Controllers\AdminController::class, 'categoryAction']);
Router::get('/api/admin/reports', [\App\Controllers\AdminController::class, 'reports']);
Router::post('/api/admin/reports/{id}/action', [\App\Controllers\AdminController::class, 'reportAction']);
Router::get('/api/admin/analytics', [\App\Controllers\AdminController::class, 'analytics']);
Router::get('/api/admin/reports.csv', [\App\Controllers\AdminController::class, 'analytics']);
Router::get('/api/admin/audit', [\App\Controllers\AdminController::class, 'audit']);
Router::get('/api/admin/settings', [\App\Controllers\AdminController::class, 'settingsGet']);
Router::patch('/api/admin/settings', [\App\Controllers\AdminController::class, 'settingsSave']);
Router::post('/api/admin/settings', [\App\Controllers\AdminController::class, 'settingsSave']);
Router::get('/api/admin/ledger-audit', [\App\Controllers\AdminController::class, 'ledgerAudit']);
Router::get('/api/admin/support', [\App\Controllers\AdminController::class, 'support']);
Router::get('/api/admin/support/{id}', [\App\Controllers\AdminController::class, 'supportGet']);
Router::post('/api/admin/support/{id}/reply', [\App\Controllers\AdminController::class, 'supportReply']);

Router::get('/api/messages', [\App\Controllers\CommsController::class, 'messages']);
Router::get('/api/messages/{id}', [\App\Controllers\CommsController::class, 'messageGet']);
Router::post('/api/messages/{id}', [\App\Controllers\CommsController::class, 'messageSend']);

Router::get('/api/notifications', [\App\Controllers\CommsController::class, 'notifications']);
Router::post('/api/notifications/read-all', [\App\Controllers\CommsController::class, 'notificationsRead']);
Router::get('/api/me/badges', [\App\Controllers\CommsController::class, 'badges']);
Router::get('/api/me/dashboard', [OrderController::class, 'clientDashboard']);
Router::get('/api/me/work', [OrderController::class, 'workerDashboard']);
Router::get('/api/me/proposals', [OrderController::class, 'myProposals']);
Router::get('/api/proposals/mine', [OrderController::class, 'myProposals']);
Router::get('/api/dashboard/client', [OrderController::class, 'clientDashboard']);
Router::get('/api/dashboard/worker', [\App\Controllers\WorkerController::class, 'dashboard']);

Router::get('/api/wallet', [\App\Controllers\WorkerController::class, 'wallet']);
Router::get('/api/wallet/transactions', [\App\Controllers\WorkerController::class, 'ledger']);
Router::get('/api/withdrawals', [\App\Controllers\WorkerController::class, 'withdrawals']);
Router::post('/api/withdrawals', [\App\Controllers\WorkerController::class, 'requestWithdraw']);
Router::post('/api/withdrawals/start', [\App\Controllers\WorkerController::class, 'startWithdraw']);
Router::post('/api/withdrawals/confirm', [\App\Controllers\WorkerController::class, 'confirmWithdraw']);
Router::post('/api/withdrawals/{id}/action', [\App\Controllers\WorkerController::class, 'payWithdraw']);
Router::get('/api/admin/withdrawals', [\App\Controllers\WorkerController::class, 'adminWithdrawals']);
Router::post('/api/admin/withdrawals/{id}/action', [\App\Controllers\WorkerController::class, 'payWithdraw']);

Router::get('/api/services', [\App\Controllers\WorkerController::class, 'servicesIndex']);
Router::get('/api/me/services', [\App\Controllers\WorkerController::class, 'services']);
Router::get('/api/me/services/{id}', [\App\Controllers\WorkerController::class, 'serviceGet']);
Router::post('/api/services', [\App\Controllers\WorkerController::class, 'serviceCreate']);
Router::patch('/api/services/{id}', [\App\Controllers\WorkerController::class, 'serviceUpdate']);
Router::post('/api/services/{id}', [\App\Controllers\WorkerController::class, 'serviceUpdate']);
Router::post('/api/services/{id}/pause', [\App\Controllers\WorkerController::class, 'servicePause']);

Router::post('/api/orders/from-service', [PaymentController::class, 'fromService']);
Router::post('/api/payments/initiate', [PaymentController::class, 'initiate']);
Router::get('/api/payments/latest', [PaymentController::class, 'latest']);
Router::get('/api/payments/{id}/receipt.pdf', [PaymentController::class, 'receipt']);
Router::get('/api/payments/{id}/receipt', [PaymentController::class, 'receipt']);
Router::get('/api/payments/{id}', [PaymentController::class, 'status']);
Router::get('/api/payments/{id}/status', [PaymentController::class, 'status']);
Router::post('/api/payments/{id}/simulate', [PaymentController::class, 'simulate']);
Router::post('/api/webhooks/paystack', [PaymentController::class, 'paystack']);
Router::post('/api/webhooks/flutterwave', [PaymentController::class, 'flutterwave']);
Router::get('/api/verification/status', [PaymentController::class, 'verificationStatus']);
Router::post('/api/verification/submit', [PaymentController::class, 'verificationSubmit']);
Router::get('/api/promotions', [PaymentController::class, 'promotions']);
Router::post('/api/promotions/{id}/purchase', [PaymentController::class, 'buyPromo']);
