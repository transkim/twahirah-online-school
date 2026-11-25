<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/home', HomeController::class)->name('home');

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

        Route::get('/plans', [Admin\PlanController::class, 'index'])->name('plans.index');
        Route::put('/plans/{plan}', [Admin\PlanController::class, 'update'])->name('plans.update')->middleware('role:admin');
    });
});
