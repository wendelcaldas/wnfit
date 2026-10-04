<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CollectionsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\OrganizationMessagingController;
use App\Http\Controllers\OrganizationUserController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\StudentAccessController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentPortalAuthController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\TwilioWebhookController;
use App\Http\Controllers\WorkoutController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::prefix('student')->group(function () {
        Route::get('/me', [StudentPortalAuthController::class, 'me']);
        Route::post('/login', [StudentPortalAuthController::class, 'login'])->middleware('throttle:10,1');
        Route::post('/logout', [StudentPortalAuthController::class, 'logout']);
        Route::put('/password', [StudentPortalAuthController::class, 'password'])->middleware(['student:password', 'throttle:10,1']);
        Route::middleware('student')->group(function () {
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
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

    Route::middleware(['auth', 'staff'])->group(function () {
        Route::get('/student-access/suggest', [StudentAccessController::class, 'suggest']);
        Route::get('/students/{student}/access', [StudentAccessController::class, 'show']);
        Route::post('/students/{student}/access', [StudentAccessController::class, 'issue']);
        Route::post('/students/{student}/workout-plans/{plan}/personalize', [WorkoutController::class, 'personalize']);
        Route::post('/workouts/{workout}/duplicate', [WorkoutController::class, 'duplicate']);
        Route::patch('/workouts/{workout}/status', [WorkoutController::class, 'status']);
        Route::get('/events', [EventController::class, 'index']);
        Route::post('/events', [EventController::class, 'save']);
        Route::patch('/events/{event}', [EventController::class, 'save']);
        Route::post('/events/{event}/close', [EventController::class, 'close']);
        Route::get('/events/{event}/registrations', [EventController::class, 'registrations']);
        Route::patch('/events/{event}/registrations/{registration}', [EventController::class, 'attendance']);
        Route::get('/dashboard', DashboardController::class);
        Route::get('/finance', FinanceController::class);
        Route::get('/collections', [CollectionsController::class, 'index']);
        Route::get('/collections/{student}', [CollectionsController::class, 'show']);
        Route::post('/collections/{student}/message', [CollectionsController::class, 'message']);
        Route::post('/collections/{student}/pay', [CollectionsController::class, 'pay']);
        Route::post('/collections/{student}/recurrence', [CollectionsController::class, 'recurrence']);
        Route::get('/schedule', [ScheduleController::class, 'index']);
        Route::get('/schedule/options', [ScheduleController::class, 'options']);
        Route::post('/schedule', [ScheduleController::class, 'store']);
        Route::put('/schedule/{schedule}', [ScheduleController::class, 'update']);
        Route::get('/workouts', [WorkoutController::class, 'index']);
        Route::get('/exercises', [ExerciseController::class, 'index']);
        Route::post('/exercises', [ExerciseController::class, 'store']);
        Route::post('/workouts', [WorkoutController::class, 'store']);
        Route::get('/workouts/{workout}', [WorkoutController::class, 'show']);
        Route::put('/workouts/{workout}', [WorkoutController::class, 'update']);
        Route::get('/organization/users', [OrganizationUserController::class, 'index']);
        Route::post('/organization/users', [OrganizationUserController::class, 'store']);
        Route::patch('/organization/users/{user}', [OrganizationUserController::class, 'update']);
        Route::get('/organization/messaging', [OrganizationMessagingController::class, 'show']);
        Route::get('/organization/messaging/twilio-templates', [OrganizationMessagingController::class, 'twilioTemplates']);
        Route::patch('/organization/messaging', [OrganizationMessagingController::class, 'update']);
        Route::get('/students/options', [StudentController::class, 'options']);
        Route::get('/students', [StudentController::class, 'index']);
        Route::post('/students', [StudentController::class, 'store']);
        Route::get('/students/{student}', [StudentController::class, 'show']);
        Route::patch('/students/{student}', [StudentController::class, 'update']);
        Route::get('/students/{student}/workouts', [StudentController::class, 'workouts']);
        Route::post('/students/{student}/workouts/{workout}', [StudentController::class, 'assignWorkout']);
        Route::post('/students/{student}/charges', [StudentController::class, 'generateCharge']);
        Route::post('/charges/{charge}/send', [StudentController::class, 'sendCharge']);
        Route::post('/charges/{charge}/pay', [StudentController::class, 'payCharge']);
        Route::post('/messages/{message}/opened', [MessageController::class, 'opened']);
        Route::post('/messages/{message}/sent', [MessageController::class, 'sent']);
        Route::patch('/messages/{message}', [MessageController::class, 'update']);
    });
});

Route::view('/', 'landing')->name('home');
Route::view('/{any?}', 'app')->where('any', '.*');
