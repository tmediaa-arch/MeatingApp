<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Domains\Meetings\Models\Meeting::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'subject' => 'required|string|max:500',
            'description' => 'nullable|string',
            'meeting_type' => 'required|string',
            'scheduled_start_at' => 'required|date',
            'scheduled_end_at' => 'required|date|after:scheduled_start_at',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'host_org_unit_id' => 'nullable|integer|exists:org_units,id',
            'priority' => 'nullable|integer|min:1|max:5',
            'confidentiality_level' => 'nullable|string|in:public,internal,confidential,secret,top_secret',
            'participant_user_ids' => 'nullable|array',
            'participant_user_ids.*' => 'integer|exists:users,id',
            'video_conference_required' => 'nullable|boolean',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'subject.required' => 'موضوع جلسه الزامی است.',
            'scheduled_start_at.required' => 'زمان شروع الزامی است.',
            'scheduled_end_at.after' => 'زمان پایان باید پس از زمان شروع باشد.',
        ];
    }
}
