<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CollectionsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventGiftController;
use App\Http\Controllers\EventParticipantController;
use App\Http\Controllers\EventPaymentController;
use App\Http\Controllers\EventPhotoController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\InternalModuleController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\OrganizationMessagingController;
use App\Http\Controllers\OrganizationUserController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\StaffNotificationController;
use App\Http\Controllers\StudentAccessController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentEventController;
use App\Http\Controllers\StudentPhysicalAssessmentController;
use App\Http\Controllers\StudentPortalAuthController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\TwilioWebhookController;
use App\Http\Controllers\WorkoutController;
use App\Http\Middleware\EnsureOrganizationModule;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

require __DIR__.'/support.php';

Route::prefix('api')->group(function () {
    Route::get('/public/schedule/{slug}', [\App\Http\Controllers\PublicScheduleController::class, 'show'])->middleware('throttle:60,1,public-schedule-read:');
    Route::get('/public/schedule/{slug}/availability', [\App\Http\Controllers\PublicScheduleController::class, 'availability'])->middleware('throttle:60,1,public-schedule-read:');
    Route::post('/public/schedule/{slug}/reservations', [\App\Http\Controllers\PublicScheduleController::class, 'store'])->middleware('throttle:10,1,public-schedule-write:');
    Route::get('/public/booking/{token}', [\App\Http\Controllers\PublicBookingController::class, 'show'])->middleware('throttle:60,1,public-booking-read:');
    Route::get('/public/booking/{token}/availability', [\App\Http\Controllers\PublicBookingController::class, 'availability'])->middleware('throttle:60,1,public-booking-read:');
    Route::post('/public/booking/{token}', [\App\Http\Controllers\PublicBookingController::class, 'store'])->middleware('throttle:10,1,public-booking-write:');
    Route::prefix('student')->group(function () {
        Route::get('/me', [StudentPortalAuthController::class, 'me']);
        Route::post('/login', [StudentPortalAuthController::class, 'login'])->middleware('throttle:10,1');
        Route::post('/logout', [StudentPortalAuthController::class, 'logout']);
        Route::put('/password', [StudentPortalAuthController::class, 'password'])->middleware(['student:password', 'throttle:10,1']);
        Route::middleware('student')->group(function () {
            Route::get('/events/{event}/photos', [EventPhotoController::class, 'index']);
            Route::post('/events/{event}/photos', [EventPhotoController::class, 'upload'])->middleware('throttle:20,1');
            Route::get('/events/{event}/photos/{photo}/file', [EventPhotoController::class, 'file']);
            Route::get('/events/{event}/gift', [EventGiftController::class, 'show']);
            Route::post('/events/{event}/gift', [EventGiftController::class, 'reveal']);
            Route::get('/events', [StudentEventController::class, 'index']);
            Route::get('/events/{event}', [StudentEventController::class, 'show']);
            Route::post('/events/{event}/feedback', [StudentEventController::class, 'feedback']);
            Route::post('/events/{event}/welcome', [StudentEventController::class, 'welcome']);
            Route::post('/events/{event}/checkin', [StudentEventController::class, 'checkin']);
            Route::post('/events/{event}/register', [PublicEventController::class, 'studentRegister']);
            Route::get('/home', [StudentPortalController::class, 'home']);
            Route::post('/sessions', [StudentPortalController::class, 'start']);
            Route::get('/sessions/{session}', [StudentPortalController::class, 'show']);
            Route::patch('/sessions/{session}', [StudentPortalController::class, 'update']);
        });
    });
    Route::prefix('public/events/{event:slug}')->group(function () {
        Route::get('/', [PublicEventController::class, 'show']);
        foreach (['register', 'recover', 'checkin', 'feedback'] as $action) {
            Route::post('/'.$action, [PublicEventController::class, $action])->middleware($action === 'recover' ? 'throttle:10,1' : 'throttle:60,1');
        }
    });
    Route::post('/webhooks/twilio/whatsapp/status', [TwilioWebhookController::class, 'whatsappStatus'])
        ->name('webhooks.twilio.whatsapp.status')
        ->withoutMiddleware(VerifyCsrfToken::class);

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/register/check-email', [AuthController::class, 'checkRegistrationEmail'])->middleware('throttle:20,1,register-email');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

    Route::middleware('auth')->prefix('internal/modules')->group(function () {
        Route::get('/', [InternalModuleController::class, 'index']);
        Route::patch('/{organizationId}', [InternalModuleController::class, 'update'])->whereNumber('organizationId');
    });

    Route::middleware(['auth', 'staff'])->group(function () {
        Route::get('/student-access/suggest', [StudentAccessController::class, 'suggest'])->middleware(EnsureOrganizationModule::class.':students');
        Route::get('/students/{student}/access', [StudentAccessController::class, 'show'])->middleware(EnsureOrganizationModule::class.':students');
        Route::post('/students/{student}/access', [StudentAccessController::class, 'issue'])->middleware(EnsureOrganizationModule::class.':students');
        Route::post('/students/{student}/workout-plans/{plan}/personalize', [WorkoutController::class, 'personalize'])->middleware(EnsureOrganizationModule::class.':workouts');
        Route::post('/workouts/{workout}/duplicate', [WorkoutController::class, 'duplicate'])->middleware(EnsureOrganizationModule::class.':workouts');
        Route::patch('/workouts/{workout}/status', [WorkoutController::class, 'status'])->middleware(EnsureOrganizationModule::class.':workouts');
        Route::get('/events', [EventController::class, 'index']);
        Route::post('/events', [EventController::class, 'save']);
        Route::patch('/events/{event}', [EventController::class, 'save']);
        Route::get('/events/{event}/photos', [EventPhotoController::class, 'index']);
        Route::get('/events/{event}/photos/{photo}/file', [EventPhotoController::class, 'file']);
        Route::patch('/events/{event}/photos/{photo}', [EventPhotoController::class, 'moderate']);
        Route::patch('/events/{event}/settings', [EventController::class, 'settings']);
        Route::post('/events/{event}/registrations/{registration}/gift-delivery', [EventGiftController::class, 'deliver']);
        Route::post('/events/{event}/registrations/{registration}/payment', [EventPaymentController::class, 'pay']);
        Route::get('/events/{event}/people', [EventController::class, 'people']);
        Route::post('/events/{event}/registrations', [EventController::class, 'addParticipant']);
        Route::post('/events/{event}/close', [EventController::class, 'close']);
        Route::get('/events/{event}/registrations', [EventController::class, 'registrations']);
        Route::get('/events/{event}/registrations/{registration}/students', [EventParticipantController::class, 'candidates']);
        Route::post('/events/{event}/registrations/{registration}/access', [EventParticipantController::class, 'access']);
        Route::patch('/events/{event}/registrations/{registration}', [EventController::class, 'attendance']);
        Route::get('/notifications', [StaffNotificationController::class, 'index']);
        Route::patch('/notifications/{notification}/read', [StaffNotificationController::class, 'read'])->whereNumber('notification');
        Route::get('/dashboard', DashboardController::class);
        Route::get('/dashboard/calendar', [DashboardController::class, 'calendar'])->middleware(EnsureOrganizationModule::class.':schedule');
        Route::get('/finance', FinanceController::class)->middleware(EnsureOrganizationModule::class.':finance');
        Route::get('/finance/income', [\App\Http\Controllers\FinanceIncomeController::class, 'index'])->middleware(EnsureOrganizationModule::class.':finance');
        Route::post('/finance/income', [\App\Http\Controllers\FinanceIncomeController::class, 'store'])->middleware(EnsureOrganizationModule::class.':finance');
        Route::get('/collections', [CollectionsController::class, 'index'])->middleware(EnsureOrganizationModule::class.':finance');
        Route::get('/collections/{student}', [CollectionsController::class, 'show'])->middleware(EnsureOrganizationModule::class.':finance');
        Route::post('/collections/{student}/message', [CollectionsController::class, 'message'])->middleware(EnsureOrganizationModule::class.':finance');
        Route::post('/collections/{student}/pay', [CollectionsController::class, 'pay'])->middleware(EnsureOrganizationModule::class.':finance');
        Route::post('/collections/{student}/recurrence', [CollectionsController::class, 'recurrence'])->middleware(EnsureOrganizationModule::class.':finance');
        Route::post('/organization/booking-links', [\App\Http\Controllers\OrganizationScheduleController::class, 'saveLink'])->middleware(EnsureOrganizationModule::class.':schedule');
        Route::put('/organization/booking-links/{link}', [\App\Http\Controllers\OrganizationScheduleController::class, 'saveLink'])->whereNumber('link')->middleware(EnsureOrganizationModule::class.':schedule');
        Route::delete('/organization/booking-links/{link}', [\App\Http\Controllers\OrganizationScheduleController::class, 'deleteLink'])->whereNumber('link')->middleware(EnsureOrganizationModule::class.':schedule');
        Route::get('/organization/schedule-settings', [\App\Http\Controllers\OrganizationScheduleController::class, 'show'])->middleware(EnsureOrganizationModule::class.':schedule');
        Route::put('/organization/schedule-settings', [\App\Http\Controllers\OrganizationScheduleController::class, 'save'])->middleware(EnsureOrganizationModule::class.':schedule');
        Route::get('/schedule', [ScheduleController::class, 'index'])->middleware(EnsureOrganizationModule::class.':schedule');
        Route::get('/schedule/options', [ScheduleController::class, 'options'])->middleware(EnsureOrganizationModule::class.':schedule');
        Route::post('/schedule', [ScheduleController::class, 'store'])->middleware(EnsureOrganizationModule::class.':schedule');
        Route::put('/schedule/{schedule}', [ScheduleController::class, 'update'])->middleware(EnsureOrganizationModule::class.':schedule');
        Route::patch('/schedule/{schedule}/cancel', [ScheduleController::class, 'cancel'])->middleware(EnsureOrganizationModule::class.':schedule');
        Route::get('/workouts', [WorkoutController::class, 'index'])->middleware(EnsureOrganizationModule::class.':workouts');
        Route::get('/exercises', [ExerciseController::class, 'index'])->middleware(EnsureOrganizationModule::class.':workouts');
        Route::post('/exercises', [ExerciseController::class, 'store'])->middleware(EnsureOrganizationModule::class.':workouts');
        Route::post('/workouts', [WorkoutController::class, 'store'])->middleware(EnsureOrganizationModule::class.':workouts');
        Route::get('/workouts/{workout}', [WorkoutController::class, 'show'])->middleware(EnsureOrganizationModule::class.':workouts');
        Route::put('/workouts/{workout}', [WorkoutController::class, 'update'])->middleware(EnsureOrganizationModule::class.':workouts');
        Route::get('/organization/units', [\App\Http\Controllers\OrganizationUnitController::class, 'index']);
        Route::post('/organization/units', [\App\Http\Controllers\OrganizationUnitController::class, 'save']);
        Route::delete('/organization/units/{unit}', [\App\Http\Controllers\OrganizationUnitController::class, 'destroy']);
        Route::put('/organization/units/{unit}', [\App\Http\Controllers\OrganizationUnitController::class, 'save']);
        Route::get('/organization/plans', [\App\Http\Controllers\OrganizationPlanController::class, 'index']);
        Route::post('/organization/plans', [\App\Http\Controllers\OrganizationPlanController::class, 'save']);
        Route::put('/organization/plans/{plan}', [\App\Http\Controllers\OrganizationPlanController::class, 'save']);
        Route::get('/organization/users', [OrganizationUserController::class, 'index']);
        Route::post('/organization/users', [OrganizationUserController::class, 'store']);
        Route::patch('/organization/users/{user}', [OrganizationUserController::class, 'update']);
        Route::delete('/organization/users/{user}', [OrganizationUserController::class, 'destroy']);
        Route::get('/organization/messaging', [OrganizationMessagingController::class, 'show']);
        Route::get('/organization/messaging/twilio-templates', [OrganizationMessagingController::class, 'twilioTemplates']);
        Route::patch('/organization/messaging', [OrganizationMessagingController::class, 'update']);
        Route::patch('/organization/messaging/events', [OrganizationMessagingController::class, 'updateEvents']);
        Route::get('/students/options', [StudentController::class, 'options'])->middleware(EnsureOrganizationModule::class.':students');
        Route::get('/students', [StudentController::class, 'index'])->middleware(EnsureOrganizationModule::class.':students,workouts,finance');
        Route::post('/students', [StudentController::class, 'store'])->middleware(EnsureOrganizationModule::class.':students');
        Route::get('/students/{student}', [StudentController::class, 'show'])->middleware(EnsureOrganizationModule::class.':students,finance');
        Route::patch('/students/{student}', [StudentController::class, 'update'])->middleware(EnsureOrganizationModule::class.':students');
        Route::post('/students/{student}/photo', [StudentController::class, 'uploadPhoto'])->middleware(EnsureOrganizationModule::class.':students');
        Route::get('/students/{student}/photo', [StudentController::class, 'photo'])->middleware(EnsureOrganizationModule::class.':students');
        Route::get('/students/{student}/physical-assessments', [StudentPhysicalAssessmentController::class, 'index'])->middleware(EnsureOrganizationModule::class.':students');
        Route::post('/students/{student}/physical-assessments', [StudentPhysicalAssessmentController::class, 'store'])->middleware(EnsureOrganizationModule::class.':students');
        Route::get('/students/{student}/physical-assessments/{assessment}', [StudentPhysicalAssessmentController::class, 'show'])->middleware(EnsureOrganizationModule::class.':students');
        Route::put('/students/{student}/physical-assessments/{assessment}', [StudentPhysicalAssessmentController::class, 'update'])->middleware(EnsureOrganizationModule::class.':students');
        Route::get('/students/{student}/workouts', [StudentController::class, 'workouts'])->middleware(EnsureOrganizationModule::class.':workouts');
        Route::post('/students/{student}/workouts/{workout}', [StudentController::class, 'assignWorkout'])->middleware(EnsureOrganizationModule::class.':workouts');
        Route::post('/students/{student}/workout-plans/{plan}/renew', [StudentController::class, 'renewWorkout'])->middleware(EnsureOrganizationModule::class.':workouts');
        Route::post('/students/{student}/charges', [StudentController::class, 'generateCharge'])->middleware(EnsureOrganizationModule::class.':finance');
        Route::post('/charges/{charge}/send', [StudentController::class, 'sendCharge'])->middleware(EnsureOrganizationModule::class.':finance');
        Route::post('/charges/{charge}/pay', [StudentController::class, 'payCharge'])->middleware(EnsureOrganizationModule::class.':finance');
        Route::post('/messages/{message}/opened', [MessageController::class, 'opened']);
        Route::post('/messages/{message}/sent', [MessageController::class, 'sent']);
        Route::patch('/messages/{message}', [MessageController::class, 'update']);
    });
});

Route::redirect('/', '/entrar');
Route::view('/site', 'landing')->name('site');
Route::view('/{any?}', 'app')->where('any', '.*');
