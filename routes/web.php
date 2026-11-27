<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordCodeController;
use App\Http\Controllers\Chat;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Learning;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Student;
use App\Http\Controllers\Teacher;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    // First sign-in or forgotten password: a code by SMS, then a new password.
    Route::get('/password/code', [PasswordCodeController::class, 'create'])->name('password.code');
    Route::post('/password/code', [PasswordCodeController::class, 'store'])->name('password.code.send');
    Route::get('/password/code/verify', [PasswordCodeController::class, 'edit'])->name('password.code.verify');
    Route::post('/password/code/verify', [PasswordCodeController::class, 'update'])->name('password.code.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/home', HomeController::class)->name('home');
    Route::get('/account/password', [AccountController::class, 'edit'])->name('account.password');
    Route::put('/account/password', [AccountController::class, 'update'])->name('account.password.update');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store')->middleware('role:admin,finance');

    Route::get('/lessons', [Learning\LessonController::class, 'index'])->name('lessons.index');
    Route::post('/lessons', [Learning\LessonController::class, 'store'])->name('lessons.store');
    Route::get('/lessons/{lesson}', [Learning\LessonController::class, 'show'])->name('lessons.show');
    Route::get('/lessons/{lesson}/file', [Learning\LessonController::class, 'file'])->name('lessons.file');
    Route::delete('/lessons/{lesson}', [Learning\LessonController::class, 'destroy'])->name('lessons.destroy');
    Route::get('/homework', [Learning\HomeworkController::class, 'index'])->name('homework.index');
    Route::post('/homework', [Learning\HomeworkController::class, 'store'])->name('homework.store');
    Route::get('/homework/{homework}', [Learning\HomeworkController::class, 'show'])->name('homework.show');
    Route::post('/homework/{homework}/submit', [Learning\HomeworkController::class, 'submit'])->name('homework.submit');
    Route::post('/homework/submissions/{submission}/mark', [Learning\HomeworkController::class, 'mark'])->name('homework.mark');
    Route::get('/homework/submissions/{submission}/file', [Learning\HomeworkController::class, 'file'])->name('homework.file');

    Route::get('/chat', [Chat\ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat', [Chat\ChatController::class, 'create'])->name('chat.create');
    Route::get('/chat/{group}', [Chat\ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat/{group}/messages', [Chat\ChatController::class, 'store'])->name('chat.store');
    Route::post('/chat/{group}/members', [Chat\ChatController::class, 'addMember'])->name('chat.members.store');
    Route::post('/chat/{group}/members/{member}', [Chat\ChatController::class, 'updateMember'])->name('chat.members.update');
    Route::get('/chat/messages/{message}/file', [Chat\ChatController::class, 'file'])->name('chat.file');
    Route::post('/chat/messages/{message}/forward', [Chat\ChatController::class, 'forward'])->name('chat.forward');
    Route::delete('/chat/messages/{message}', [Chat\ChatController::class, 'destroy'])->name('chat.destroy');

    Route::middleware('role:student')->prefix('my')->name('my.')->group(function () {
        Route::get('/classes', [Student\ClassController::class, 'index'])->name('classes');
        Route::post('/classes', [Student\ClassController::class, 'store'])->name('classes.store');
        Route::delete('/classes/{booking}', [Student\ClassController::class, 'destroy'])->name('classes.destroy');
        Route::post('/classes/{booking}/join', [Student\ClassController::class, 'join'])->name('classes.join');

        Route::get('/subscription', [Student\SubscriptionController::class, 'index'])->name('subscribe');
        Route::post('/subscription', [Student\SubscriptionController::class, 'store'])->name('subscribe.store');
        Route::get('/payments/{payment}', [Student\SubscriptionController::class, 'show'])->name('payments.show');
    });

    Route::middleware('role:teacher')->prefix('teach')->name('teach.')->group(function () {
        Route::get('/classes', [Teacher\ClassController::class, 'index'])->name('classes');
        Route::post('/availability', [Teacher\ClassController::class, 'storeAvailability'])->name('availability.store');
        Route::delete('/availability/{availability}', [Teacher\ClassController::class, 'destroyAvailability'])->name('availability.destroy');
    });

    // Teachers can keep their own registers; admins and finance see everyone's.
    Route::middleware('role:admin,finance,teacher')->group(function () {
        Route::get('/admin/attendance', [Admin\AttendanceController::class, 'index'])->name('admin.attendance');
        Route::post('/admin/attendance/{enrolment}', [Admin\AttendanceController::class, 'update'])->name('admin.attendance.update');
    });

    Route::middleware('role:admin,finance')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::get('/students', [Admin\StudentController::class, 'index'])->name('students.index');
        Route::get('/students/create', [Admin\StudentController::class, 'create'])->name('students.create');
        Route::post('/students', [Admin\StudentController::class, 'store'])->name('students.store');
        Route::get('/students/{student}', [Admin\StudentController::class, 'show'])->name('students.show');
        Route::post('/students/{student}/enrol', [Admin\StudentController::class, 'enrol'])->name('students.enrol');
        Route::post('/students/{student}/enrolments/{enrolment}/teacher', [Admin\StudentController::class, 'changeTeacher'])->name('students.teacher');
        Route::post('/students/{student}/payments', [Admin\StudentController::class, 'recordPayment'])->name('students.payments');
        Route::post('/students/{student}/grant', [Admin\StudentController::class, 'grant'])->name('students.grant');

        Route::get('/teachers', [Admin\TeacherController::class, 'index'])->name('teachers.index');
        Route::post('/teachers', [Admin\TeacherController::class, 'store'])->name('teachers.store');
        Route::get('/teachers/{teacher}', [Admin\TeacherController::class, 'show'])->name('teachers.show');
        Route::post('/teachers/{teacher}/courses', [Admin\TeacherController::class, 'addCourse'])->name('teachers.courses.store');
        Route::delete('/teachers/{teacher}/courses/{teacherCourse}', [Admin\TeacherController::class, 'removeCourse'])->name('teachers.courses.destroy');
        Route::post('/teachers/{teacher}/availability', [Admin\TeacherController::class, 'addAvailability'])->name('teachers.availability.store');
        Route::delete('/teachers/{teacher}/availability/{availability}', [Admin\TeacherController::class, 'removeAvailability'])->name('teachers.availability.destroy');

        Route::get('/courses', [Admin\CourseController::class, 'index'])->name('courses.index');

        Route::get('/payments', [Admin\PaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments/{payment}/confirm', [Admin\PaymentController::class, 'confirm'])->name('payments.confirm');
        Route::post('/payments/{payment}/reject', [Admin\PaymentController::class, 'reject'])->name('payments.reject');

        Route::get('/section/{slug}', Admin\SectionController::class)->name('section');

        Route::get('/plans', [Admin\PlanController::class, 'index'])->name('plans.index');
        Route::put('/plans/{plan}', [Admin\PlanController::class, 'update'])->name('plans.update')->middleware('role:admin');
    });
});
