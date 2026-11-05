<?php

namespace App\Http\Resources\V1;

use App\Support\Loc;
use Illuminate\Http\Request;

/**
 * صفحة الدورة: المنهج بعناوين الدروس فقط (بدون فيديو أو محتوى).
 * يُشترط تحميل sections.lessons وrequirements وlearningOutcomes مسبقًا.
 */
class CourseDetailResource extends CourseResource
{
    public array $state = [];

    public function toArray(Request $request): array
    {
        $sections = $this->sections;
        $lessons  = $sections->flatMap->lessons;

        return parent::toArray($request) + [
            'description'    => Loc::pick($this->description_ar, $this->description_en),
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'requirements'   => $this->requirements->map(fn ($r) => Loc::pick($r->requirement_ar, $r->requirement_en))->values(),
            'outcomes'       => $this->learningOutcomes->map(fn ($o) => Loc::pick($o->outcome_ar, $o->outcome_en))->values(),
            'stats'          => ['lessons' => $lessons->count(), 'minutes' => (int) $lessons->sum('duration_minutes')],
            'sections'       => $sections->map(fn ($s) => [
                'id'      => $s->id,
                'title'   => Loc::pick($s->title_ar, $s->title_en),
                'lessons' => $s->lessons->map(fn ($l) => [
                    'id'               => $l->id,
                    'title'            => Loc::pick($l->title_ar, $l->title_en),
                    'type'             => $l->content_type,
                    'duration_minutes' => $l->duration_minutes,
                    'is_free_preview'  => (bool) $l->is_free_preview,
                ])->values(),
            ])->values(),
            'state'          => $this->state,
        ];
    }

    public function withState(array $state): static
    {
        $this->state = $state;

        return $this;
    }
}
