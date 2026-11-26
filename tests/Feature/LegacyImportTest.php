<?php

namespace Tests\Feature;

use App\Models\ChatGroup;
use App\Models\Enrolment;
use App\Models\User;
use Database\Seeders\CourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LegacyImportTest extends TestCase
{
    use RefreshDatabase;

    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CourseSeeder::class);

        $this->folder = sys_get_temp_dir().'/legacy-import-'.uniqid();
        File::ensureDirectoryExists($this->folder);
        File::put($this->folder.'/teachers.json', json_encode(['teachers' => [
            ['id' => '11', 'title' => 'SHK', 'first_name' => 'HALIIMAH', 'last_name' => 'NASSOZI', 'phone' => '0700000011', 'email' => '', 'gender' => 'Female', 'is_deleted' => '0',
                'courses' => [['name' => 'YASSARNA AL QURAN', 'mode' => 'WHATSAPP']]],
        ]]));
        File::put($this->folder.'/students.json', json_encode([
            ['SID' => '7530', 'student_no' => 'T/26/7530', 'name' => 'LUNJA  LUKIA', 'phone' => '0752 084 475', 'gender' => 'female', 'admission_date' => '2026-11-24', 'courses' => [
                ['course' => 'YASSARNA AL QURAN', 'mode' => 'WHATSAPP', 'status' => 1, 'level' => null, 'teacher' => 'Nassozi  Haliimah', 'seen' => '2026-11-25', 'balance' => -40000, 'when' => '', 'days' => 'Mon, Tue, Wed'],
                ['course' => 'QURAN', 'mode' => 'ZOOM', 'status' => 2, 'level' => null, 'teacher' => 'Someone Gone', 'seen' => '2025-05-06', 'balance' => 15000, 'when' => '2026-11-19', 'days' => ''],
            ]],
            ['SID' => '5507', 'student_no' => 'T/24/5507', 'name' => 'Old Student', 'phone' => '0740723226', 'gender' => 'female', 'admission_date' => '2024-01-10', 'courses' => [
                ['course' => 'SWALAH', 'mode' => 'WHATSAPP', 'status' => 3, 'level' => null, 'teacher' => '', 'seen' => '', 'balance' => 0, 'when' => '', 'days' => ''],
                ['course' => 'UNKNOWN COURSE', 'mode' => 'WHATSAPP', 'status' => 1, 'level' => null, 'teacher' => '', 'seen' => '', 'balance' => 0, 'when' => '', 'days' => ''],
            ]],
        ]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);
        parent::tearDown();
    }

    public function test_students_teachers_courses_and_balances_come_across(): void
    {
        $this->artisan('school:import-legacy', ['folder' => $this->folder])->assertSuccessful();

        $teacher = User::where('role', 'teacher')->where('legacy_id', 11)->sole();
        $this->assertSame('Haliimah Nassozi', $teacher->name);
        $this->assertTrue($teacher->teacherCourses()->exists());

        $student = User::where('legacy_id', 7530)->sole();
        $this->assertSame(['Lunja Lukia', 'T/26/7530', '0752084475', 'female', true], [$student->name, $student->student_number, $student->phone, $student->gender, $student->active]);

        $studying = Enrolment::where('student_id', $student->id)->where('status', 'active')->sole();
        $this->assertSame([$teacher->id, 3, -40000], [$studying->teacher_id, $studying->days_per_week, $studying->legacy_balance_ugx]);
        $this->assertTrue(ChatGroup::where('kind', 'class')->where('teacher_id', $teacher->id)->sole()->members()->where('users.id', $student->id)->exists());

        $stopped = Enrolment::where('student_id', $student->id)->where('status', 'cancelled')->sole();
        $this->assertSame(['2026-11-19', null, 15000], [$stopped->ended_on, $stopped->teacher_id, $stopped->legacy_balance_ugx]);

        $old = User::where('legacy_id', 5507)->sole();
        $this->assertFalse($old->active);
        $this->assertSame(1, Enrolment::where('student_id', $old->id)->count());
    }

    public function test_imported_people_cannot_sign_in_until_the_office_sets_a_password(): void
    {
        $this->artisan('school:import-legacy', ['folder' => $this->folder])->assertSuccessful();

        $this->post('/login', ['login' => '0752084475', 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_running_the_import_again_updates_instead_of_copying(): void
    {
        $this->artisan('school:import-legacy', ['folder' => $this->folder])->assertSuccessful();
        $this->artisan('school:import-legacy', ['folder' => $this->folder])->assertSuccessful();

        $this->assertSame(2, User::where('role', 'student')->count());
        $this->assertSame(1, User::where('role', 'teacher')->count());
        $this->assertSame(3, Enrolment::count());
    }

    public function test_a_missing_file_fails_without_importing(): void
    {
        $this->artisan('school:import-legacy', ['folder' => $this->folder.'/nowhere'])->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_it_downloads_from_the_old_portal_then_imports(): void
    {
        config(['school.legacy_portal' => ['url' => 'https://portal.test', 'username' => 'office', 'password' => 'secret']]);
        $students = json_decode(File::get($this->folder.'/students.json'), true);
        Http::fake([
            'portal.test/Signin' => Http::response('<form></form>'),
            'portal.test/User_mgt/user_login_process' => Http::response('<html></html>'),
            'portal.test/students/roster' => Http::response(['rows' => $students, 'total' => 2]),
            'portal.test/Teachers/directory' => Http::response(File::get($this->folder.'/teachers.json')),
        ]);
        $download = $this->folder.'/download';

        $this->artisan('school:import-legacy', ['folder' => $download, '--from-portal' => true])->assertSuccessful();

        $this->assertFileExists($download.'/students.json');
        $this->assertSame(2, User::where('role', 'student')->count());
        Http::assertSent(fn ($request) => $request->url() === 'https://portal.test/User_mgt/user_login_process' && $request['username'] === 'office');
        Http::assertNotSent(fn ($request) => ! in_array($request->method(), ['GET', 'POST'], true));
    }

    public function test_a_wrong_portal_password_stops_the_import(): void
    {
        config(['school.legacy_portal' => ['url' => 'https://portal.test', 'username' => 'office', 'password' => 'wrong']]);
        Http::fake(['portal.test/*' => Http::response('<html>Sign in</html>')]);

        $this->artisan('school:import-legacy', ['folder' => $this->folder.'/download', '--from-portal' => true])
            ->expectsOutputToContain('Could not sign in')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }
}
