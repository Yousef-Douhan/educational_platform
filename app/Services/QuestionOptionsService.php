<?php

namespace App\Services;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Support\Arr;

class QuestionOptionsService
{
    /** يحوّل مدخلات الطلب إلى صفوف خيارات موحّدة حسب نوع السؤال */
    public function normalize(string $type, array $input, ?Question $existing = null): array
    {
        if ($type === 'true_false') {
            $answer = $input['tf_answer'] ?? 'true';

            return [
                ['id' => $this->existingId($existing, 'true'),  'option_text' => 'true',  'is_correct' => $answer === 'true',  'sort_order' => 1],
                ['id' => $this->existingId($existing, 'false'), 'option_text' => 'false', 'is_correct' => $answer === 'false', 'sort_order' => 2],
            ];
        }

        return collect($input['options'] ?? [])->values()->map(fn(array $o, int $i) => [
            'id'          => isset($o['id']) ? (int) $o['id'] : null,
            'option_text' => trim($o['option_text']),
            // في أسئلة النص كل الإجابات المقبولة صحيحة
            'is_correct'  => $type === 'text' ? true : filter_var($o['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'sort_order'  => $i + 1,
        ])->all();
    }

    /** يطابق الخيارات بالمعرّف: يحدّث الموجود وينشئ الجديد ويحذف مؤقتًا ما غاب (يحفظ تاريخ الإجابات) */
    public function sync(Question $question, array $rows): void
    {
        $existing = $question->options()->get()->keyBy('id');
        $keep     = [];

        foreach ($rows as $row) {
            $payload = Arr::only($row, ['option_text', 'is_correct', 'sort_order']);
            $id      = $row['id'] ?? null;

            if ($id && $existing->has($id)) {
                $existing[$id]->update($payload);
                $keep[] = $id;
            } else {
                $keep[] = $question->options()->create($payload)->id;
            }
        }

        QuestionOption::where('question_id', $question->id)->whereNotIn('id', $keep)->delete();
    }

    /** هل يغيّر التعديل بنية السؤال (نوعه أو درجته أو مجموعة خياراته أو صحتها)؟ */
    public function structureChanged(Question $question, string $type, int $points, array $rows): bool
    {
        $existing = $question->options()->get()->keyBy('id');

        if ($question->question_type !== $type || $question->points !== $points) {
            return true;
        }

        $ids = collect($rows)->pluck('id')->filter()->map(fn($i) => (int) $i)->all();

        if (count($rows) !== $existing->count() || count($ids) !== count($rows) || array_diff($ids, $existing->keys()->all())) {
            return true;
        }

        foreach ($rows as $row) {
            if ((bool) $existing[$row['id']]->is_correct !== (bool) $row['is_correct']) {
                return true;
            }
        }

        return false;
    }

    private function existingId(?Question $question, string $text): ?int
    {
        return $question?->options()->where('option_text', $text)->value('id');
    }
}
