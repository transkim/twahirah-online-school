<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Plan;
use App\Models\TeacherAvailability;
use App\Models\TeacherCourse;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\EnrolmentService;
use App\Services\SubscriptionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Made-up teachers and students for the online test copy, so the school can try every page
 * without real student records on the internet. Nobody here is a real person; phones are 0700 000 xxx.
 *
 * Runs once. Set DEMO_PASSWORD to also create the sign-ins teacher.demo and student.demo.
 */
class DemoSeeder extends Seeder
{
    private const TEACHERS = ['Amina Nalubega', 'Yusuf Kato', 'Hadija Namusoke', 'Ismail Ssemanda', 'Zainab Nakato', 'Umar Mukasa', 'Rehema Babirye', 'Idris Lubega'];

    private const FIRST = ['Abdul', 'Aisha', 'Bashir', 'Fatuma', 'Hamza', 'Halima', 'Juma', 'Khadija', 'Musa', 'Mariam', 'Nuhu', 'Rukia', 'Sulaiman', 'Safia', 'Twaha', 'Zubeda', 'Hassan', 'Asha', 'Imran', 'Shamim'];

    private const LAST = ['Kigozi', 'Namuli', 'Ssali', 'Nabukenya', 'Mutebi', 'Nansubuga', 'Waiswa', 'Nakimuli', 'Kasule', 'Nabirye'];

    public function run(EnrolmentService $enrolments, SubscriptionService $subscriptions, AttendanceService $attendance): void
    {
        if (User::where('username', 'demo.teacher.1')->exists()) {
            return;
        }

        $password = Hash::make(Str::random(40));
        $courses = Course::all()->keyBy(fn (Course $course): string => $course->name.'|'.$course->mode);
        $taught = ['Quran|chat', 'Yassarna al Quran|chat', 'Arabic Language|chat', 'Quran|zoom', 'Yassarna al Quran|zoom', 'Swalah|chat', 'Tajweed|chat', 'Arabic Language|zoom'];

        $teachers = collect(self::TEACHERS)->map(function (string $name, int $i) use ($password, $courses, $taught): User {
            $teacher = User::create([
                'name' => $name, 'username' => 'demo.teacher.'.($i + 1), 'role' => User::ROLE_TEACHER,
                'phone' => sprintf('0700000%03d', $i + 1), 'gender' => $i % 2 ? 'male' : 'female', 'password' => $password,
            ]);
            foreach ([$taught[$i], $taught[($i + 3) % count($taught)]] as $key) {
                TeacherCourse::firstOrCreate(['teacher_id' => $teacher->id, 'course_id' => $courses[$key]->id, 'course_level_id' => null]);
            }
            foreach ([1, 3, 5] as $weekday) {
                TeacherAvailability::create(['teacher_id' => $teacher->id, 'weekday' => ($weekday + $i) % 7, 'start_time' => sprintf('%02d:00', 8 + $i % 6), 'end_time' => sprintf('%02d:00', 11 + $i % 6)]);
            }

            return $teacher;
        });

        $chatMonth = Plan::where(['mode' => 'chat', 'cycle' => 'month'])->first();
        $chatWeek = Plan::where(['mode' => 'chat', 'cycle' => 'week'])->first();
        $zoomMonth = Plan::where(['mode' => 'zoom', 'days_per_week' => 3, 'cycle' => 'month'])->first();

        foreach (range(1, 60) as $n) {
            $student = User::create([
                'name' => self::FIRST[$n % count(self::FIRST)].' '.self::LAST[intdiv($n, 2) % count(self::LAST)],
                'student_number' => sprintf('DEMO/%03d', $n), 'phone' => sprintf('0700000%03d', 100 + $n),
                'gender' => $n % 2 ? 'female' : 'male', 'joined_on' => now()->subDays(3 * $n)->toDateString(), 'password' => $password,
            ]);

            $key = $taught[$n % count($taught)];
            $enrolment = $enrolments->enrol($student, $courses[$key], null, str_ends_with($key, 'zoom') ? 3 : null);
            if ($n % 7 === 0) {
                $enrolment->update(['legacy_balance_ugx' => -40000]);
            }

            if ($n % 5 !== 0) {
                $plan = str_ends_with($key, 'zoom') ? $zoomMonth : ($n % 3 ? $chatMonth : $chatWeek);
                $payment = $subscriptions->startPayment($student, $plan, $n % 2 ? 'mtn' : 'airtel', $student->phone);
                $subscriptions->confirmPayment($payment, 'DEMO-'.$n, now()->subDays($n % 20));
                $attendance->recordSignIn($student);
            }
        }

        if ($demoPassword = env('DEMO_PASSWORD')) {
            $teachers->first()->update(['username' => 'teacher.demo', 'password' => $demoPassword]);
            User::where('student_number', 'DEMO/001')->update(['username' => 'student.demo', 'password' => Hash::make($demoPassword)]);
        }
    }
}
