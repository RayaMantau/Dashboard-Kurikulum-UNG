<?php

namespace Tests\Feature;

use App\Enums\CourseType;
use App\Models\Course;
use App\Models\CurriculumCourse;
use App\Models\Semester;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CurriculumCourseSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_creation_uses_server_identity_even_with_forged_attributes_and_relationship(): void
    {
        $course = Course::factory()->create(['code' => 'IF101', 'name' => 'Algoritma']);
        $attributes = CurriculumCourse::factory()->for($course)->make()->getAttributes();
        $attachment = new CurriculumCourse($attributes);
        $attachment->course_code = 'FAKE';
        $attachment->course_name = 'FAKE';
        $attachment->setRelation('course', Course::factory()->make(['code' => 'FORGED']));
        $attachment->save();

        $this->assertSame('IF101', $attachment->fresh()->course_code);
        $this->assertSame('Algoritma', $attachment->fresh()->course_name);
    }

    public function test_new_attachment_uses_current_master_while_existing_snapshot_is_preserved(): void
    {
        $course = Course::factory()->create(['code' => 'IF101', 'name' => 'Algoritma']);
        $first = CurriculumCourse::factory()->for($course)->create();
        $course->update(['code' => 'IF201', 'name' => 'Dasar Pemrograman']);
        $second = CurriculumCourse::factory()->for($course)->create();

        $this->assertSame('IF101', $first->fresh()->course_code);
        $this->assertSame('Algoritma', $first->fresh()->course_name);
        $this->assertSame('IF201', $second->course_code);
        $this->assertSame('Dasar Pemrograman', $second->course_name);
    }

    public function test_structural_updates_and_forged_mass_assignment_do_not_refresh_identity(): void
    {
        $course = Course::factory()->create(['code' => 'IF101', 'name' => 'Algoritma']);
        $attachment = CurriculumCourse::factory()->for($course)->create();
        $semester = Semester::factory()->create(['curriculum_id' => $attachment->curriculum_id]);
        $course->update(['code' => 'IF201', 'name' => 'Dasar Pemrograman']);
        $attachment->update([
            'semester_id' => $semester->id, 'sks' => 4, 'course_type' => CourseType::ELECTIVE,
            'prerequisite_notes' => 'Lulus pengantar', 'is_active' => false,
            'course_code' => 'FAKE', 'course_name' => 'FAKE',
        ]);
        $attachment->refresh();

        $this->assertSame('IF101', $attachment->course_code);
        $this->assertSame('Algoritma', $attachment->course_name);
        $this->assertSame($semester->id, $attachment->semester_id);
        $this->assertSame('4.00', $attachment->sks);
        $this->assertSame(CourseType::ELECTIVE, $attachment->course_type);
        $this->assertSame('Lulus pengantar', $attachment->prerequisite_notes);
        $this->assertFalse($attachment->is_active);
    }

    public static function immutableFields(): array
    {
        return [['course_code'], ['course_name'], ['course_id']];
    }

    #[DataProvider('immutableFields')]
    public function test_direct_identity_mutation_is_rejected(string $field): void
    {
        $attachment = CurriculumCourse::factory()->create();
        $before = $attachment->fresh()->getAttributes();
        $attachment->{$field} = $field === 'course_id' ? Course::factory()->create()->id : 'FORGED';

        try {
            $attachment->save();
            $this->fail('Identity mutation should fail.');
        } catch (DomainException) {
            $this->assertSame($before, $attachment->fresh()->getAttributes());
        }
    }

    public function test_existing_snapshot_cannot_be_recaptured(): void
    {
        $attachment = CurriculumCourse::factory()->create();
        $this->expectException(DomainException::class);
        $attachment->captureCourseIdentity();
    }

    public function test_factory_captures_overridden_course_without_model_events(): void
    {
        $course = Course::factory()->create(['code' => 'IF101', 'name' => 'Algoritma']);
        CurriculumCourse::withoutEvents(function () use ($course): void {
            foreach ([
                CurriculumCourse::factory()->for($course),
                CurriculumCourse::factory()->state(['course_id' => $course->id, 'course_code' => 'FAKE', 'course_name' => 'FAKE']),
            ] as $factory) {
                $attachment = $factory->create();
                $this->assertSame($course->id, $attachment->course_id);
                $this->assertSame('IF101', $attachment->course_code);
                $this->assertSame('Algoritma', $attachment->course_name);
            }
        });
    }

    public function test_database_seeder_captures_snapshots_with_model_events_disabled(): void
    {
        $this->seed(DatabaseSeeder::class);
        $attachments = CurriculumCourse::with('course')->get();
        $this->assertCount(8, $attachments);
        foreach ($attachments as $attachment) {
            $this->assertSame($attachment->course->code, $attachment->course_code);
            $this->assertSame($attachment->course->name, $attachment->course_name);
        }
    }

    public function test_unusable_master_identity_is_rejected(): void
    {
        $course = Course::factory()->create(['name' => '   ']);
        $this->expectException(DomainException::class);
        CurriculumCourse::factory()->for($course)->create();
    }
}
