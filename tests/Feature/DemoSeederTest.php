<?php

namespace Tests\Feature;

use App\Models\Enrolment;
use App\Models\User;
use Database\Seeders\CourseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_school_is_made_once_with_every_student_placed_with_a_teacher(): void
    {
        $this->seed([PlanSeeder::class, CourseSeeder::class, DemoSeeder::class, DemoSeeder::class]);

        $this->assertSame(60, User::where('role', 'student')->count());
        $this->assertSame(8, User::where('role', 'teacher')->count());
        $this->assertSame(0, Enrolment::whereNull('teacher_id')->count());
        $this->assertSame(0, User::where('role', 'student')->where('phone', 'not like', '0700000%')->count());

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/students')->assertOk()->assertSee('DEMO/001');
    }
}
