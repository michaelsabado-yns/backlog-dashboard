<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BacklogProjectService
{
    private const FIELD_TYPE_NAMES = [
        1 => 'Text',
        2 => 'TextArea',
        3 => 'Number',
        4 => 'Date',
        5 => 'Single list',
        6 => 'Multiple list',
        7 => 'Checkbox',
        8 => 'Radio',
    ];

    private const PERSON_IN_CHARGE_PATTERNS = [
        '/^担当者$/u',
        '/^担当$/u',
        '/^person in charge$/i',
        '/person[\s_-]*in[\s_-]*charge/i',
    ];

    private const SUB_ASSIGNEE_PATTERNS = [
        '/^sub person in charge$/i',
        '/サブ担当/u',
        '/副担当/u',
        '/sub[\s_-]*assignee/i',
        '/sub[\s_-]*person/i',
        '/sub[\s_-]*in[\s_-]*charge/i',
    ];

    private const QA_IN_CHARGE_PATTERNS = [
        '/^qa in charge$/i',
        '/^qa担当$/u',
        '/^qa担当者$/u',
        '/qa[\s_-]*in[\s_-]*charge/i',
        '/qa担当/u',
    ];

    private const SUB_QA_PATTERNS = [
        '/^sub qa in charge$/i',
        '/サブqa担当/ui',
        '/副qa担当/ui',
        '/sub[\s_-]*qa[\s_-]*in[\s_-]*charge/i',
        '/sub[\s_-]*qa$/i',
    ];

    private const REVIEWER_PATTERNS = [
        '/^reviewer$/i',
        '/^レビュアー$/u',
        '/^レビュー担当$/u',
        '/^レビュー担当者$/u',
        '/reviewer/i',
        '/レビュー担当/u',
    ];

    private const SUB_REVIEWER_PATTERNS = [
        '/^sub reviewer$/i',
        '/^サブレビュアー$/u',
        '/^副レビュアー$/u',
        '/^副レビュー$/u',
        '/sub[\s_-]*reviewer/i',
        '/サブレビュアー/u',
        '/副レビュー/u',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getProjectsWithDetails(string $apiKey): array
    {
        $trimmedApiKey = trim($apiKey);

        if ($trimmedApiKey === '') {
            return [];
        }

        return Cache::remember(
            $this->cacheKey($trimmedApiKey),
            now()->addMinutes(30),
            fn () => $this->buildProjectsWithDetails($trimmedApiKey),
        );
    }

    /**
     * @param  array<int, int>|null  $projectIds
     * @return array<int, array<string, mixed>>
     */
    public function getProjectMappings(string $apiKey, ?array $projectIds = null): array
    {
        $projects = $this->getProjectsWithDetails($apiKey);

        if ($projectIds !== null) {
            $allowed = array_flip($projectIds);
            $projects = array_values(array_filter(
                $projects,
                static fn (array $project): bool => isset($allowed[(int) $project['id']]),
            ));
        }

        return $projects;
    }

    public function clearCachedMappings(string $apiKey): void
    {
        $trimmedApiKey = trim($apiKey);

        if ($trimmedApiKey !== '') {
            Cache::forget($this->cacheKey($trimmedApiKey));
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildProjectsWithDetails(string $apiKey): array
    {
        $baseUrl = rtrim((string) config('backlog.url'), '/');
        $response = Http::get($baseUrl.'/api/v2/projects', [
            'apiKey' => $apiKey,
        ]);

        if (! $response->successful()) {
            return [];
        }

        $projects = [];

        foreach ($response->json() ?? [] as $project) {
            $projectKey = $project['projectKey'] ?? null;
            $projectId = isset($project['id']) ? (int) $project['id'] : null;

            if (! is_string($projectKey) || $projectKey === '' || $projectId === null) {
                continue;
            }

            $members = $this->fetchProjectMembers($baseUrl, $apiKey, $projectKey);
            $customFields = $this->fetchProjectCustomFields($baseUrl, $apiKey, $projectKey);
            $detectedFields = $this->detectFieldRoles($customFields);
            $customFieldsWithRoles = $this->attachRolesToFields($customFields);

            $statuses = $this->fetchProjectStatuses($baseUrl, $apiKey, $projectKey);

            $projects[] = [
                'id' => $projectId,
                'project_key' => $projectKey,
                'name' => is_string($project['name'] ?? null) ? $project['name'] : $projectKey,
                'archived' => (bool) ($project['archived'] ?? false),
                'member_count' => count($members),
                'members' => $members,
                'custom_fields' => $customFieldsWithRoles,
                'statuses' => $statuses,
                'uses_standard_assignee' => true,
                'person_in_charge_field' => $detectedFields['person_in_charge'],
                'sub_person_in_charge_fields' => $detectedFields['sub_person_in_charge'],
                'qa_in_charge_field' => $detectedFields['qa_in_charge'],
                'sub_qa_in_charge_fields' => $detectedFields['sub_qa_in_charge'],
                'reviewer_field' => $detectedFields['reviewer'],
                'sub_reviewer_fields' => $detectedFields['sub_reviewer'],
            ];
        }

        usort($projects, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $projects;
    }

    /**
     * @return array<int, array{id: int, user_id: string, name: string}>
     */
    private function fetchProjectMembers(string $baseUrl, string $apiKey, string $projectKey): array
    {
        $response = Http::get($baseUrl.'/api/v2/projects/'.$projectKey.'/users', [
            'apiKey' => $apiKey,
        ]);

        if (! $response->successful()) {
            return [];
        }

        $members = [];

        foreach ($response->json() ?? [] as $member) {
            if (! isset($member['id'])) {
                continue;
            }

            $members[] = [
                'id' => (int) $member['id'],
                'user_id' => is_string($member['userId'] ?? null) ? $member['userId'] : '',
                'name' => is_string($member['name'] ?? null) ? $member['name'] : '',
            ];
        }

        return $members;
    }

    /**
     * @return array<int, array{id: int, name: string, color: string|null, display_order: int}>
     */
    private function fetchProjectStatuses(string $baseUrl, string $apiKey, string $projectKey): array
    {
        $response = Http::get($baseUrl.'/api/v2/projects/'.$projectKey.'/statuses', [
            'apiKey' => $apiKey,
        ]);

        if (! $response->successful()) {
            return [];
        }

        $statuses = [];

        foreach ($response->json() ?? [] as $status) {
            if (! isset($status['id'])) {
                continue;
            }

            $name = is_string($status['name'] ?? null) ? $status['name'] : '';

            if ($name === '') {
                continue;
            }

            $statuses[] = [
                'id' => (int) $status['id'],
                'name' => $name,
                'color' => is_string($status['color'] ?? null) ? $status['color'] : null,
                'display_order' => isset($status['displayOrder']) ? (int) $status['displayOrder'] : 0,
            ];
        }

        usort(
            $statuses,
            static fn (array $a, array $b): int => $a['display_order'] <=> $b['display_order']
                ?: strcmp($a['name'], $b['name']),
        );

        return $statuses;
    }

    /**
     * Merge status catalogs from selected projects by normalized status name.
     *
     * @param  array<int, int>|null  $projectIds
     * @return array<int, array{key: string, name: string, color: string|null, display_order: int}>
     */
    public function getMergedStatusColumns(string $apiKey, ?array $projectIds = null): array
    {
        $projects = $this->getProjectMappings($apiKey, $projectIds);

        /** @var array<string, array{key: string, name: string, color: string|null, orders: array<int, int>}> $byKey */
        $byKey = [];

        foreach ($projects as $project) {
            if (($project['archived'] ?? false) === true) {
                continue;
            }

            foreach ($project['statuses'] ?? [] as $status) {
                if (! is_array($status)) {
                    continue;
                }

                $name = trim((string) ($status['name'] ?? ''));

                if ($name === '') {
                    continue;
                }

                $key = mb_strtolower($name);

                if (! isset($byKey[$key])) {
                    $byKey[$key] = [
                        'key' => $key,
                        'name' => $name,
                        'color' => is_string($status['color'] ?? null) && $status['color'] !== ''
                            ? $status['color']
                            : null,
                        'orders' => [],
                    ];
                }

                if (
                    ($byKey[$key]['color'] === null || $byKey[$key]['color'] === '')
                    && is_string($status['color'] ?? null)
                    && $status['color'] !== ''
                ) {
                    $byKey[$key]['color'] = $status['color'];
                }

                $byKey[$key]['orders'][] = (int) ($status['display_order'] ?? 0);
            }
        }

        $columns = [];

        foreach ($byKey as $column) {
            $orders = $column['orders'];
            $avgOrder = $orders === []
                ? 0
                : (int) round(array_sum($orders) / count($orders));

            $columns[] = [
                'key' => $column['key'],
                'name' => $column['name'],
                'color' => $column['color'],
                'display_order' => $avgOrder,
            ];
        }

        usort(
            $columns,
            static fn (array $a, array $b): int => $a['display_order'] <=> $b['display_order']
                ?: strcmp($a['name'], $b['name']),
        );

        return $columns;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchProjectCustomFields(string $baseUrl, string $apiKey, string $projectKey): array
    {
        $response = Http::get($baseUrl.'/api/v2/projects/'.$projectKey.'/customFields', [
            'apiKey' => $apiKey,
        ]);

        if (! $response->successful()) {
            return [];
        }

        $fields = [];

        foreach ($response->json() ?? [] as $field) {
            if (! isset($field['id'])) {
                continue;
            }

            $fieldId = (int) $field['id'];
            $typeId = isset($field['typeId']) ? (int) $field['typeId'] : null;
            $name = is_string($field['name'] ?? null) ? $field['name'] : '';

            $fields[] = [
                'id' => $fieldId,
                'name' => $name,
                'type_id' => $typeId,
                'type_name' => self::FIELD_TYPE_NAMES[$typeId] ?? 'Unknown',
                'api_filter' => 'customField_'.$fieldId.'[]',
                'ui_filter_example' => 'attribute_'.$fieldId.'_4_*={listItemId}',
                'items' => array_map(
                    static fn (array $item): array => [
                        'id' => (int) ($item['id'] ?? 0),
                        'name' => is_string($item['name'] ?? null) ? $item['name'] : '',
                    ],
                    is_array($field['items'] ?? null) ? $field['items'] : [],
                ),
            ];
        }

        return $fields;
    }

    /**
     * @param  array<int, array<string, mixed>>  $customFields
     * @return array{
     *     person_in_charge: array<string, mixed>|null,
     *     sub_person_in_charge: array<int, array<string, mixed>>,
     *     qa_in_charge: array<string, mixed>|null,
     *     sub_qa_in_charge: array<int, array<string, mixed>>,
     *     reviewer: array<string, mixed>|null,
     *     sub_reviewer: array<int, array<string, mixed>>
     * }
     */
    private function detectFieldRoles(array $customFields): array
    {
        $personInCharge = null;
        $subFields = [];
        $qaInCharge = null;
        $subQaFields = [];
        $reviewer = null;
        $subReviewerFields = [];

        foreach ($customFields as $field) {
            $name = (string) $field['name'];
            $role = $this->resolveFieldRole($name);
            $fieldWithRole = array_merge($field, ['role' => $role]);

            if ($role === 'person_in_charge' && $personInCharge === null) {
                $personInCharge = $fieldWithRole;
            }

            if ($role === 'sub_person_in_charge') {
                $subFields[] = $fieldWithRole;
            }

            if ($role === 'qa_in_charge' && $qaInCharge === null) {
                $qaInCharge = $fieldWithRole;
            }

            if ($role === 'sub_qa_in_charge') {
                $subQaFields[] = $fieldWithRole;
            }

            if ($role === 'reviewer' && $reviewer === null) {
                $reviewer = $fieldWithRole;
            }

            if ($role === 'sub_reviewer') {
                $subReviewerFields[] = $fieldWithRole;
            }
        }

        return [
            'person_in_charge' => $personInCharge,
            'sub_person_in_charge' => $subFields,
            'qa_in_charge' => $qaInCharge,
            'sub_qa_in_charge' => $subQaFields,
            'reviewer' => $reviewer,
            'sub_reviewer' => $subReviewerFields,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $customFields
     * @return array<int, array<string, mixed>>
     */
    private function attachRolesToFields(array $customFields): array
    {
        return array_map(function (array $field): array {
            $name = (string) $field['name'];

            return array_merge($field, ['role' => $this->resolveFieldRole($name)]);
        }, $customFields);
    }

    private function resolveFieldRole(string $name): ?string
    {
        if ($this->matchesSubReviewerName($name)) {
            return 'sub_reviewer';
        }

        if ($this->matchesSubQaInChargeName($name)) {
            return 'sub_qa_in_charge';
        }

        if ($this->matchesSubAssigneeName($name)) {
            return 'sub_person_in_charge';
        }

        if ($this->matchesReviewerName($name)) {
            return 'reviewer';
        }

        if ($this->matchesQaInChargeName($name)) {
            return 'qa_in_charge';
        }

        if ($this->matchesPersonInChargeName($name)) {
            return 'person_in_charge';
        }

        return null;
    }

    private function matchesPersonInChargeName(string $name): bool
    {
        if (preg_match('/サブ|副/u', $name) === 1) {
            return false;
        }

        foreach (self::PERSON_IN_CHARGE_PATTERNS as $pattern) {
            if (preg_match($pattern, $name) === 1) {
                return true;
            }
        }

        return false;
    }

    private function matchesSubAssigneeName(string $name): bool
    {
        if ($this->matchesSubQaInChargeName($name)) {
            return false;
        }

        foreach (self::SUB_ASSIGNEE_PATTERNS as $pattern) {
            if (preg_match($pattern, $name) === 1) {
                return true;
            }
        }

        return false;
    }

    private function matchesQaInChargeName(string $name): bool
    {
        if (preg_match('/サブ|副|sub/ui', $name) === 1) {
            return false;
        }

        if (preg_match('/actual[\s_-]*hours|実績/u', $name) === 1) {
            return false;
        }

        foreach (self::QA_IN_CHARGE_PATTERNS as $pattern) {
            if (preg_match($pattern, $name) === 1) {
                return true;
            }
        }

        return false;
    }

    private function matchesSubQaInChargeName(string $name): bool
    {
        if (preg_match('/actual[\s_-]*hours|実績/u', $name) === 1) {
            return false;
        }

        foreach (self::SUB_QA_PATTERNS as $pattern) {
            if (preg_match($pattern, $name) === 1) {
                return true;
            }
        }

        return false;
    }

    private function matchesReviewerName(string $name): bool
    {
        if (preg_match('/サブ|副|sub/ui', $name) === 1) {
            return false;
        }

        foreach (self::REVIEWER_PATTERNS as $pattern) {
            if (preg_match($pattern, $name) === 1) {
                return true;
            }
        }

        return false;
    }

    private function matchesSubReviewerName(string $name): bool
    {
        foreach (self::SUB_REVIEWER_PATTERNS as $pattern) {
            if (preg_match($pattern, $name) === 1) {
                return true;
            }
        }

        return false;
    }

    private function cacheKey(string $apiKey): string
    {
        return 'backlog.project_mappings.'.hash('sha256', $apiKey);
    }
}
