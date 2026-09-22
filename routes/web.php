<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\CollectionsController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\OrganizationUserController;
use App\Http\Controllers\OrganizationMessagingController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\WorkoutController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\TwilioWebhookController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::prefix('public/events/{event:slug}')->group(function () {
        Route::get('/', [\App\Http\Controllers\PublicEventController::class, 'show']);
        foreach (['register', 'recover', 'checkin', 'feedback'] as $action) {
            Route::post('/'.$action, [\App\Http\Controllers\PublicEventController::class, $action])->middleware($action === 'recover' ? 'throttle:10,1' : 'throttle:60,1');
        }
    });
    Route::post('/webhooks/twilio/whatsapp/status', [TwilioWebhookController::class, 'whatsappStatus'])
        ->name('webhooks.twilio.whatsapp.status')
        ->withoutMiddleware(VerifyCsrfToken::class);

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

    Route::middleware('auth')->group(function () {
        Route::get('/events', [\App\Http\Controllers\EventController::class, 'index']);
        Route::post('/events', [\App\Http\Controllers\EventController::class, 'save']);
        Route::patch('/events/{event}', [\App\Http\Controllers\EventController::class, 'save']);
        Route::post('/events/{event}/close', [\App\Http\Controllers\EventController::class, 'close']);
        Route::get('/events/{event}/registrations', [\App\Http\Controllers\EventController::class, 'registrations']);
        Route::patch('/events/{event}/registrations/{registration}', [\App\Http\Controllers\EventController::class, 'attendance']);
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

Route::view('/{any?}', 'app')->where('any', '.*');
