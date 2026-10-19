<?php

namespace App\Models;

use App\Enums\CourseType;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumCourse extends Model
{
    use HasFactory;

    protected $fillable = [
        'curriculum_id',
        'semester_id',
        'course_id',
        'sks',
        'course_type',
        'prerequisite_notes',
        'is_active',
    ];

    protected $casts = [
        'sks' => 'decimal:2',
        'course_type' => CourseType::class,
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $attachment) => $attachment->captureCourseIdentity());

        static::updating(function (self $attachment): void {
            if ($attachment->isDirty(['course_id', 'course_code', 'course_name'])) {
                throw new DomainException('Identitas mata kuliah pada kurikulum tidak dapat diubah.');
            }
        });
    }

    /**
     * Also called explicitly by factories/seeders that suppress model events.
     * Bulk writes bypass these guards and must honor the same snapshot contract.
     */
    public function captureCourseIdentity(): void
    {
        if ($this->exists) {
            throw new DomainException('Snapshot hanya dibuat saat mata kuliah ditambahkan.');
        }

        $course = Course::query()->findOrFail($this->course_id);

        foreach (['code', 'name'] as $field) {
            if (! is_string($course->{$field}) || trim($course->{$field}) === '' || mb_strlen($course->{$field}) > 255) {
                throw new DomainException('Kode dan nama sumber mata kuliah harus valid untuk snapshot.');
            }
        }

        $this->course_code = $course->code;
        $this->course_name = $course->name;
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
