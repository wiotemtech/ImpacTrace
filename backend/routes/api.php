<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectFormController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SubmissionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PublicPortalController;
use App\Http\Controllers\Api\PublicAuthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PublicationAdminController;
use App\Http\Controllers\Api\ParController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\KnowledgeController;
use App\Http\Controllers\Api\PresentationController;
use App\Http\Controllers\Api\PublicationCommentController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\FundraisingController;
use App\Http\Controllers\ApiDonateController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // --- COTIA platform level: onboarding client organisations (super_admin only) ---
    Route::middleware('role:super_admin')->group(function () {
        Route::apiResource('organizations', OrganizationController::class)->except(['destroy']);
    });

    // --- Everything below belongs to ONE organisation. The 'org' middleware
    //     rejects super_admin (and any user with no organization_id) up front;
    //     every controller additionally double-checks organization_id on the
    //     specific record being touched, so an ID from another organisation
    //     can never be guessed into view. ---
    // --- Projects are visible to the super-admin across all organisations, while org users still see only their organisation's projects. ---
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);

    Route::middleware('org')->group(function () {
        // --- Org admin: users & roles (ED of this organisation only) ---
        Route::middleware('role:super_admin,ed')->group(function () {
            Route::apiResource('users', UserController::class)->except(['show']);
        });

        // --- Projects (create/edit: super_admin on behalf of org, ed, meo) ---
        Route::middleware('role:super_admin,ed,meo')->group(function () {
            Route::post('/projects', [ProjectController::class, 'store']);
            Route::put('/projects/{project}', [ProjectController::class, 'update']);
        });

        // --- Dynamic forms (create/edit: super_admin on behalf of org, ed, meo) ---
        Route::get('/projects/{project}/forms', [ProjectFormController::class, 'index']);
        Route::get('/forms/{projectForm}', [ProjectFormController::class, 'show']);
        Route::middleware('role:super_admin,ed,meo')->group(function () {
            Route::post('/projects/{project}/forms', [ProjectFormController::class, 'store']);
            Route::put('/forms/{projectForm}', [ProjectFormController::class, 'update']);
        });

        // --- Data collection: all org roles can submit; super_admin and org admins can work on behalf of staff ---
        Route::get('/submissions', [SubmissionController::class, 'index']);
        Route::get('/submissions/{submission}', [SubmissionController::class, 'show']);
        Route::post('/submissions/check-duplicate', [SubmissionController::class, 'checkDuplicate']);
        Route::post('/submissions', [SubmissionController::class, 'store']);
        Route::middleware('role:super_admin,ed,meo,po')->group(function () {
            Route::post('/submissions/{submission}/review', [SubmissionController::class, 'review']);
        });

        // --- Attendance ---
        Route::get('/attendance', [AttendanceController::class, 'forDate']);

        // --- Reports ---
        Route::get('/reports', [ReportController::class, 'index']);
        Route::post('/reports/generate', [ReportController::class, 'generate']);
        Route::get('/reports/{report}/pdf', [ReportController::class, 'downloadPdf']);
        Route::put('/reports/{report}', [ReportController::class, 'update']);
    });
});

// Public knowledge bank: summaries/search are public; full content is protected.
Route::get('/public/publications', [PublicPortalController::class, 'index']);
Route::get('/public/publications/{slug}', [PublicPortalController::class, 'show']);
Route::get('/public/publications/{slug}/comments', [PublicationCommentController::class, 'index']);
Route::get('/public/plans', [KnowledgeController::class, 'publicPlans']);
Route::get('/public/issues', [KnowledgeController::class, 'publicIssues']);
Route::post('/public/auth/register', [PublicAuthController::class, 'register']);
Route::post('/public/auth/login', [PublicAuthController::class, 'login']);
Route::post('/public/support', [SupportController::class, 'store']);
Route::post('/payments/webhook', [PaymentController::class, 'webhook']);
Route::get('/public/fundraisers', [FundraisingController::class, 'publicIndex']);
Route::get('/public/fundraisers/{slug}', [FundraisingController::class, 'publicShow']);
Route::post('/public/fundraisers/{slug}/donate', [FundraisingController::class, 'initiateDonation']);
Route::post('/public/fundraising-donations/{donation}/reference', [FundraisingController::class, 'submitReference']);
Route::post('/fundraising/webhook', [FundraisingController::class, 'webhook']);
Route::post('/initiate-donation', [ApiDonateController::class, 'createPayment']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/public/auth/logout', [PublicAuthController::class, 'logout']);
    Route::post('/payments/packages/{package}/initiate', [PaymentController::class, 'initiate']);
    Route::get('/payments/merchant-details', [PaymentController::class, 'merchantDetails']);
    Route::post('/payments/{payment}/reference', [PaymentController::class, 'submitReference']);
    Route::get('/public/publications/{slug}/access', [PaymentController::class, 'access']);
    Route::get('/public/publications/{slug}/download', [PaymentController::class, 'download']);
    Route::post('/public/publications/{slug}/comments', [PublicationCommentController::class, 'store']);
    Route::get('/finance/summary', [FinanceController::class, 'summary']);
    Route::get('/finance/transactions', [FinanceController::class, 'transactions']);
    Route::get('/finance/categories', [FinanceController::class, 'categories']);
    Route::post('/finance/categories', [FinanceController::class, 'storeCategory'])->middleware('role:super_admin,ed,meo');
    Route::delete('/finance/categories/{category}', [FinanceController::class, 'destroyCategory'])->middleware('role:super_admin,ed,meo');
    Route::get('/finance/export', [FinanceController::class, 'export']);
    Route::get('/finance/fundraising', [FundraisingController::class, 'financeSummary']);
    Route::get('/support', [SupportController::class, 'index'])->middleware('role:super_admin,customer_service');
    Route::put('/support/{supportRequest}', [SupportController::class, 'update'])->middleware('role:super_admin,customer_service');

    // Platform publishing and commercial controls.
    Route::middleware('role:super_admin,ed,meo')->group(function () {
        Route::get('/admin/publications', [PublicationAdminController::class, 'index']);
        Route::post('/admin/publications/cover', [PublicationAdminController::class, 'uploadCover']);
        Route::post('/admin/publications', [PublicationAdminController::class, 'store']);
        Route::put('/admin/publications/{publication}', [PublicationAdminController::class, 'update']);
        Route::post('/admin/publications/{publication}/packages', [PublicationAdminController::class, 'package']);
        Route::get('/admin/par/{project}', [ParController::class, 'index']);
        Route::post('/admin/par/{project}', [ParController::class, 'store']);
        Route::put('/admin/par/{parCycle}', [ParController::class, 'update']);
    });
    Route::middleware('role:super_admin,ed,meo')->group(function () {
        Route::post('/finance/import', [FinanceController::class, 'import']);
        Route::get('/knowledge/geography', [KnowledgeController::class, 'geography']);
        Route::post('/knowledge/geography', [KnowledgeController::class, 'storeGeography']);
        Route::get('/knowledge/plans', [KnowledgeController::class, 'plans']);
        Route::post('/knowledge/plans', [KnowledgeController::class, 'storePlan']);
        Route::get('/knowledge/meetings', [KnowledgeController::class, 'meetings']);
        Route::post('/knowledge/meetings', [KnowledgeController::class, 'storeMeeting']);
        Route::get('/knowledge/issues', [KnowledgeController::class, 'issues']);
        Route::post('/knowledge/issues', [KnowledgeController::class, 'storeIssue']);
        Route::get('/presentations', [PresentationController::class, 'index']);
        Route::post('/presentations', [PresentationController::class, 'store']);
        Route::put('/presentations/{deck}', [PresentationController::class, 'update']);
    });
    Route::middleware('role:super_admin')->group(function () {
        Route::post('/admin/publications/{publication}/publish', [PublicationAdminController::class, 'publish']);
        Route::get('/admin/payments/pending', [PaymentController::class, 'pending']);
        Route::post('/admin/payments/{payment}/verify', [PaymentController::class, 'verify']);
        Route::get('/admin/payments/access-report', [PaymentController::class, 'accessReport']);
        Route::put('/admin/comments/{comment}', [PublicationCommentController::class, 'moderate']);
        Route::get('/admin/fundraisers', [FundraisingController::class, 'adminIndex']);
        Route::get('/admin/fundraising-donations/pending', [FundraisingController::class, 'adminDonations']);
        Route::post('/admin/fundraisers/photo', [FundraisingController::class, 'uploadPhoto']);
        Route::post('/admin/fundraisers', [FundraisingController::class, 'store']);
        Route::post('/admin/fundraisers/{campaign}/publish', [FundraisingController::class, 'publish']);
        Route::post('/admin/fundraising-donations/{donation}/verify', [FundraisingController::class, 'verifyDonation']);
    });
});
