<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;
        $projectId = $this->input('project_id');

        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')->where(function (Builder $query) use ($userId) {
                $query->where(function (Builder $subQuery) use ($userId) {
                    $subQuery->where('user_id', $userId)
                        ->orWhereIn('id', function (Builder $sub) use ($userId) {
                            $sub->select('project_id')
                                ->from('project_members')
                                ->where('user_id', $userId)
                                ->whereIn('role', ['admin', 'editor']);
                        })
                        ->orWhereIn('id', function (Builder $sub) use ($userId) {
                            $sub->select('id')
                                ->from('projects')
                                ->whereIn('organization_id', function (Builder $orgSub) use ($userId) {
                                    $orgSub->select('organization_id')
                                        ->from('organization_members')
                                        ->where('user_id', $userId)
                                        ->whereIn('role', ['admin', 'editor']);
                                });
                        });
                })->whereNull('archived_at');
            })],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:pending,in_progress,completed'],
            'priority' => ['required', 'in:low,medium,high'],
            'due_date' => ['nullable', 'date', Rule::requiredIf($this->filled('frequency'))],
            'frequency' => ['nullable', 'in:daily,weekly,monthly,yearly'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'distinct', Rule::exists('tags', 'id')],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(function (Builder $query) use ($projectId) {
                $query->where('status', UserStatus::Active->value)
                    ->where(function (Builder $memberQuery) use ($projectId) {
                        $memberQuery->whereIn('id', function (Builder $sub) use ($projectId) {
                            $sub->select('user_id')->from('projects')->where('id', $projectId);
                        })->orWhereIn('id', function (Builder $sub) use ($projectId) {
                            $sub->select('user_id')->from('project_members')->where('project_id', $projectId);
                        })->orWhereIn('id', function (Builder $sub) use ($projectId) {
                            $sub->select('user_id')->from('organization_members')
                                ->whereIn('organization_id', function (Builder $orgSub) use ($projectId) {
                                    $orgSub->select('organization_id')->from('projects')->where('id', $projectId);
                                });
                        });
                    });
            })],
        ];
    }
}
