<?php

namespace Database\Seeders;

use App\Enums\CourseType;
use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\Faculty;
use App\Models\Semester;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DashboardKurikulumSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Seeder demo hanya boleh dijalankan pada lingkungan local atau testing.');
        }

        $faculty = Faculty::query()->create([
            'name' => 'Fakultas Teknik',
            'code' => 'FT',
            'slug' => 'fakultas-teknik',
            'description' => 'Fakultas Teknik Universitas Negeri Gorontalo.',
            'is_active' => true,
        ]);

        $studyProgram = StudyProgram::query()->create([
            'faculty_id' => $faculty->id,
            'name' => 'Teknik Sipil',
            'code' => 'TS',
            'degree' => 'S1',
            'slug' => 'teknik-sipil',
            'description' => 'Program Studi Teknik Sipil.',
            'accreditation' => 'Baik Sekali',
            'website' => null,
            'is_active' => true,
        ]);

        $universityAdmin = User::query()->create([
            'name' => 'Admin Universitas',
            'email' => 'admin.universitas@example.test',
            'password' => Hash::make('password'),
            'role' => UserRole::UNIVERSITY_ADMIN,
            'is_active' => true,
        ]);

        $facultyAdmin = User::query()->create([
            'name' => 'Admin Fakultas Teknik',
            'email' => 'admin.fakultas@example.test',
            'password' => Hash::make('password'),
            'role' => UserRole::FACULTY_ADMIN,
            'faculty_id' => $faculty->id,
            'is_active' => true,
        ]);

        $studyProgramAdmin = User::query()->create([
            'name' => 'Admin Prodi Teknik Sipil',
            'email' => 'admin.prodi@example.test',
            'password' => Hash::make('password'),
            'role' => UserRole::STUDY_PROGRAM_ADMIN,
            'faculty_id' => $faculty->id,
            'study_program_id' => $studyProgram->id,
            'is_active' => true,
        ]);

        $headOfStudyProgram = User::query()->create([
            'name' => 'Kaprodi Teknik Sipil',
            'email' => 'kaprodi@example.test',
            'password' => Hash::make('password'),
            'role' => UserRole::HEAD_OF_STUDY_PROGRAM,
            'faculty_id' => $faculty->id,
            'study_program_id' => $studyProgram->id,
            'is_active' => true,
        ]);

        $curriculum = Curriculum::query()->create([
            'study_program_id' => $studyProgram->id,
            'name' => 'Kurikulum 2024',
            'year' => 2024,
            'effective_year' => 2024,
            'total_sks' => 144,
            'total_semesters' => 8,
            'description' => 'Kurikulum Program Studi Teknik Sipil Tahun 2024.',
            'status' => CurriculumStatus::DRAFT,
            'created_by' => $studyProgramAdmin->id,
            'updated_by' => $studyProgramAdmin->id,
        ]);

        $semesterData = [
            1 => 20,
            2 => 20,
            3 => 18,
            4 => 18,
            5 => 18,
            6 => 18,
            7 => 16,
            8 => 16,
        ];

        $semesters = [];

        foreach ($semesterData as $number => $sks) {
            $semesters[$number] = Semester::query()->create([
                'curriculum_id' => $curriculum->id,
                'semester_number' => $number,
                'name' => 'Semester '.$number,
                'total_sks' => $sks,
            ]);
        }

        $courseData = [
            ['code' => 'TS101', 'name' => 'Matematika', 'semester' => 1, 'sks' => 3],
            ['code' => 'TS102', 'name' => 'Fisika Dasar', 'semester' => 1, 'sks' => 3],
            ['code' => 'TS103', 'name' => 'Menggambar Teknik', 'semester' => 1, 'sks' => 3],
            ['code' => 'TS104', 'name' => 'Mekanika Teknik', 'semester' => 2, 'sks' => 3],
            ['code' => 'TS105', 'name' => 'Statistika', 'semester' => 2, 'sks' => 3],
            ['code' => 'TS106', 'name' => 'Bahan Bangunan', 'semester' => 3, 'sks' => 3],
            ['code' => 'TS107', 'name' => 'Struktur Beton', 'semester' => 4, 'sks' => 4],
            ['code' => 'TS108', 'name' => 'Struktur Baja', 'semester' => 5, 'sks' => 3],
        ];

        foreach ($courseData as $data) {
            $course = Course::query()->create([
                'study_program_id' => $studyProgram->id,
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => null,
            ]);

            $attachment = new CurriculumCourse([
                'curriculum_id' => $curriculum->id,
                'semester_id' => $semesters[$data['semester']]->id,
                'course_id' => $course->id,
                'sks' => $data['sks'],
                'course_type' => CourseType::REQUIRED,
                'prerequisite_notes' => null,
                'is_active' => true,
            ]);
            $attachment->captureCourseIdentity();
            $attachment->save();
        }
    }
}
