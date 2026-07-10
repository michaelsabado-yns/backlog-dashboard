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

            $projects[] = [
                'id' => $projectId,
                'project_key' => $projectKey,
                'name' => is_string($project['name'] ?? null) ? $project['name'] : $projectKey,
                'archived' => (bool) ($project['archived'] ?? false),
                'member_count' => count($members),
                'members' => $members,
                'custom_fields' => $customFieldsWithRoles,
                'uses_standard_assignee' => false,
                'person_in_charge_field' => $detectedFields['person_in_charge'],
                'sub_person_in_charge_fields' => $detectedFields['sub_person_in_charge'],
                'qa_in_charge_field' => $detectedFields['qa_in_charge'],
                'sub_qa_in_charge_fields' => $detectedFields['sub_qa_in_charge'],
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
     * @param  array<int, int>  $configuredSubFieldIds
     * @return array{
     *     person_in_charge: array<string, mixed>|null,
     *     sub_person_in_charge: array<int, array<string, mixed>>,
     *     qa_in_charge: array<string, mixed>|null,
     *     sub_qa_in_charge: array<int, array<string, mixed>>
     * }
     */
    private function detectFieldRoles(array $customFields): array
    {
        $personInCharge = null;
        $subFields = [];
        $qaInCharge = null;
        $subQaFields = [];

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
        }

        return [
            'person_in_charge' => $personInCharge,
            'sub_person_in_charge' => $subFields,
            'qa_in_charge' => $qaInCharge,
            'sub_qa_in_charge' => $subQaFields,
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
        if ($this->matchesSubQaInChargeName($name)) {
            return 'sub_qa_in_charge';
        }

        if ($this->matchesSubAssigneeName($name)) {
            return 'sub_person_in_charge';
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

    private function cacheKey(string $apiKey): string
    {
        return 'backlog.project_mappings.'.hash('sha256', $apiKey);
    }
}
