<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Read-only Backlog issue client.
 */
class BacklogIssueService
{
    public function __construct(
        private readonly BacklogProjectService $projectService,
    ) {}

    /**
     * Issues where the current user is assignee or holds a role custom field.
     *
     * @param  array<int, int>|null  $projectIds
     * @param  array<string, mixed>|null  $user  Pre-resolved /users/myself payload
     * @return array<int, array{
     *     issue_key: string,
     *     summary: string,
     *     project_id: int|null,
     *     project_key: string|null,
     *     project_name: string|null,
     *     status_id: int|null,
     *     status_name: string|null,
     *     status_color: string|null,
     *     status_display_order: int|null,
     *     assignee_name: string|null,
     *     roles: array<int, string>,
     *     priority_name: string|null,
     *     actual_hours: float,
     *     backlog_url: string,
     *     updated_at: string|null
     * }>
     */
    public function getMyIssues(
        string $apiKey,
        ?array $projectIds = null,
        ?string $updatedDate = null,
        ?array $user = null,
    ): array {
        $trimmedApiKey = trim($apiKey);

        if ($trimmedApiKey === '') {
            return [];
        }

        $resolvedUser = $user ?? $this->getMyself($trimmedApiKey);

        if ($resolvedUser === null) {
            return [];
        }

        $projects = $this->projectService->getProjectMappings($trimmedApiKey, $projectIds);

        if ($projectIds !== null && $projectIds === []) {
            return [];
        }

        if ($projects === []) {
            return [];
        }

        $projectsById = [];
        $activeProjects = [];

        foreach ($projects as $project) {
            if (($project['archived'] ?? false) === true) {
                continue;
            }

            $projectId = (int) $project['id'];
            $projectsById[$projectId] = $project;
            $activeProjects[] = $project;
        }

        if ($activeProjects === []) {
            return [];
        }

        $dateFilters = [];

        if ($updatedDate !== null) {
            $dateFilters['updatedSince'] = $updatedDate;
            $dateFilters['updatedUntil'] = $updatedDate;
        }

        /** @var array<int, array<string, mixed>> $filterSets */
        $filterSets = [];
        /** @var array<int, array{role: string, project: array<string, mixed>|null}> $queryMeta */
        $queryMeta = [];

        $filterSets[] = array_merge($dateFilters, [
            'projectId' => array_map(static fn (array $project): int => (int) $project['id'], $activeProjects),
            'assigneeId' => [(int) $resolvedUser['id']],
        ]);
        $queryMeta[] = ['role' => 'assignee', 'project' => null];

        foreach ($activeProjects as $project) {
            $baseFilters = array_merge($dateFilters, [
                'projectId' => [(int) $project['id']],
            ]);

            $roleFields = [
                'person_in_charge' => $this->singleFieldList($project['person_in_charge_field'] ?? null),
                'sub_person_in_charge' => $project['sub_person_in_charge_fields'] ?? [],
                'qa_in_charge' => $this->singleFieldList($project['qa_in_charge_field'] ?? null),
                'sub_qa_in_charge' => $project['sub_qa_in_charge_fields'] ?? [],
                'reviewer' => $this->singleFieldList($project['reviewer_field'] ?? null),
                'sub_reviewer' => $project['sub_reviewer_fields'] ?? [],
            ];

            foreach ($roleFields as $role => $fields) {
                foreach ($fields as $field) {
                    if (! is_array($field) || ! isset($field['id'])) {
                        continue;
                    }

                    $filterValues = $this->resolveCustomFieldFilterValues(
                        $field,
                        $resolvedUser,
                        $project['members'] ?? [],
                    );

                    if ($filterValues === []) {
                        continue;
                    }

                    $filterSets[] = array_merge($baseFilters, [
                        'customField_'.(int) $field['id'] => $filterValues,
                    ]);
                    $queryMeta[] = ['role' => $role, 'project' => $project];
                }
            }
        }

        $queryResults = $this->fetchIssueQueriesConcurrently($trimmedApiKey, $filterSets);

        /** @var array<string, array{issue: array<string, mixed>, roles: array<int, string>, project: array<string, mixed>}> $matched */
        $matched = [];

        foreach ($queryResults as $index => $issues) {
            $meta = $queryMeta[$index] ?? null;

            if ($meta === null) {
                continue;
            }

            if ($meta['project'] === null) {
                foreach ($issues as $issue) {
                    $projectId = isset($issue['projectId']) ? (int) $issue['projectId'] : 0;
                    $project = $projectsById[$projectId] ?? null;

                    if ($project === null) {
                        continue;
                    }

                    $this->mergeMatchedIssues($matched, [$issue], $meta['role'], $project);
                }

                continue;
            }

            $this->mergeMatchedIssues($matched, $issues, $meta['role'], $meta['project']);
        }

        return $this->normalizeBoardIssues($matched);
    }

    /**
     * Build board payload: merged status columns + my issues.
     *
     * @param  array<int, int>|null  $projectIds
     * @return array{
     *     myself: array{id: int, name: string, user_id: string}|null,
     *     columns: array<int, array{key: string, name: string, color: string|null, display_order: int}>,
     *     issues: array<int, array<string, mixed>>
     * }
     */
    public function getMyBoard(string $apiKey, ?array $projectIds = null): array
    {
        $trimmedApiKey = trim($apiKey);
        $myselfPayload = null;
        $myself = $this->getMyself($trimmedApiKey);

        if (is_array($myself) && isset($myself['id'])) {
            $myselfPayload = [
                'id' => (int) $myself['id'],
                'name' => is_string($myself['name'] ?? null) ? $myself['name'] : '',
                'user_id' => is_string($myself['userId'] ?? null) ? $myself['userId'] : '',
            ];
        }

        $columns = $this->projectService->getMergedStatusColumns($trimmedApiKey, $projectIds);
        $issues = $this->getMyIssues($trimmedApiKey, $projectIds, null, $myself);

        $knownKeys = array_flip(array_column($columns, 'key'));

        foreach ($issues as $issue) {
            $statusName = trim((string) ($issue['status_name'] ?? ''));

            if ($statusName === '') {
                $statusName = 'Unknown';
            }

            $key = mb_strtolower($statusName);

            if (isset($knownKeys[$key])) {
                continue;
            }

            $columns[] = [
                'key' => $key,
                'name' => $statusName,
                'color' => is_string($issue['status_color'] ?? null) ? $issue['status_color'] : null,
                'display_order' => (int) ($issue['status_display_order'] ?? 999999),
            ];
            $knownKeys[$key] = true;
        }

        usort(
            $columns,
            static fn (array $a, array $b): int => $a['display_order'] <=> $b['display_order']
                ?: strcmp($a['name'], $b['name']),
        );

        return [
            'myself' => $myselfPayload,
            'columns' => $columns,
            'issues' => $issues,
        ];
    }

    /**
     * Fetch a single issue by its key (e.g. "BIGVISION-430").
     *
     * @return array<string, mixed>|null
     */
    public function getIssueByKey(string $apiKey, string $issueKey): ?array
    {
        $trimmedApiKey = trim($apiKey);
        $trimmedKey = trim($issueKey);

        if ($trimmedApiKey === '' || $trimmedKey === '') {
            return null;
        }

        $baseUrl = rtrim((string) config('backlog.url'), '/');
        $response = Http::get($baseUrl.'/api/v2/issues/'.$trimmedKey, [
            'apiKey' => $trimmedApiKey,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();

        return is_array($payload) && isset($payload['id']) ? $payload : null;
    }

    /**
     * Fetch current status and metadata for the given issue keys.
     *
     * @param  array<int, string>  $issueKeys
     * @return array<string, array{issue_status: string|null, issue_status_color: string|null, issue_type: string|null, created_user_id: int|null, created_user_name: string|null, custom_fields: array}>
     */
    public function getStatusesByIssueKeys(string $apiKey, array $issueKeys): array
    {
        $trimmedApiKey = trim($apiKey);

        if ($trimmedApiKey === '') {
            return [];
        }

        $uniqueKeys = array_values(array_unique(array_filter(
            $issueKeys,
            static fn (mixed $key): bool => is_string($key) && $key !== '',
        )));

        if ($uniqueKeys === []) {
            return [];
        }

        $statuses = [];

        foreach ($uniqueKeys as $issueKey) {
            $issue = $this->getIssueByKey($trimmedApiKey, $issueKey);

            if ($issue === null) {
                continue;
            }

            $status = is_array($issue['status'] ?? null) ? $issue['status'] : [];
            $issueType = is_array($issue['issueType'] ?? null) ? $issue['issueType'] : [];
            $createdUser = is_array($issue['createdUser'] ?? null) ? $issue['createdUser'] : [];

            $statuses[$issueKey] = [
                'issue_status' => is_string($status['name'] ?? null) ? $status['name'] : null,
                'issue_status_color' => is_string($status['color'] ?? null) ? $status['color'] : null,
                'issue_type' => is_string($issueType['name'] ?? null) ? $issueType['name'] : null,
                'created_user_id' => isset($createdUser['id']) ? (int) $createdUser['id'] : null,
                'created_user_name' => is_string($createdUser['name'] ?? null) ? $createdUser['name'] : null,
                'custom_fields' => is_array($issue['customFields'] ?? null) ? $issue['customFields'] : [],
            ];
        }

        return $statuses;
    }


    /**
     * @param  array<string, mixed>|null  $field
     * @return array<int, array<string, mixed>>
     */
    private function singleFieldList(?array $field): array
    {
        return is_array($field) ? [$field] : [];
    }

    /**
     * @param  array<string, array{issue: array<string, mixed>, roles: array<int, string>, project: array<string, mixed>}>  $matched
     * @param  array<int, array<string, mixed>>  $issues
     * @param  array<string, mixed>  $project
     */
    private function mergeMatchedIssues(array &$matched, array $issues, string $role, array $project): void
    {
        foreach ($issues as $issue) {
            $issueKey = $issue['issueKey'] ?? null;

            if (! is_string($issueKey) || $issueKey === '') {
                continue;
            }

            if (! isset($matched[$issueKey])) {
                $matched[$issueKey] = [
                    'issue' => $issue,
                    'roles' => [$role],
                    'project' => $project,
                ];

                continue;
            }

            $matched[$issueKey]['issue'] = $issue;
            $matched[$issueKey]['roles'][] = $role;
            $matched[$issueKey]['roles'] = array_values(array_unique($matched[$issueKey]['roles']));
        }
    }

    /**
     * @param  array<string, array{issue: array<string, mixed>, roles: array<int, string>, project: array<string, mixed>}>  $matched
     * @return array<int, array<string, mixed>>
     */
    private function normalizeBoardIssues(array $matched): array
    {
        $baseUrl = rtrim((string) config('backlog.url'), '/');
        $normalized = [];

        foreach ($matched as $entry) {
            $issue = $entry['issue'];
            $project = $entry['project'];
            $issueKey = $issue['issueKey'] ?? null;

            if (! is_string($issueKey) || $issueKey === '') {
                continue;
            }

            $status = is_array($issue['status'] ?? null) ? $issue['status'] : [];
            $assignee = is_array($issue['assignee'] ?? null) ? $issue['assignee'] : null;
            $priority = is_array($issue['priority'] ?? null) ? $issue['priority'] : null;

            $normalized[] = [
                'issue_key' => $issueKey,
                'summary' => is_string($issue['summary'] ?? null) ? $issue['summary'] : '',
                'project_id' => isset($project['id']) ? (int) $project['id'] : (isset($issue['projectId']) ? (int) $issue['projectId'] : null),
                'project_key' => is_string($project['project_key'] ?? null) ? $project['project_key'] : null,
                'project_name' => is_string($project['name'] ?? null) ? $project['name'] : null,
                'status_id' => isset($status['id']) ? (int) $status['id'] : null,
                'status_name' => is_string($status['name'] ?? null) ? $status['name'] : null,
                'status_color' => is_string($status['color'] ?? null) ? $status['color'] : null,
                'status_display_order' => isset($status['displayOrder']) ? (int) $status['displayOrder'] : null,
                'assignee_name' => is_string($assignee['name'] ?? null) ? $assignee['name'] : null,
                'roles' => array_values($entry['roles']),
                'priority_name' => is_string($priority['name'] ?? null) ? $priority['name'] : null,
                'actual_hours' => isset($issue['actualHours']) ? (float) $issue['actualHours'] : 0.0,
                'backlog_url' => $baseUrl.'/view/'.$issueKey,
                'updated_at' => is_string($issue['updated'] ?? null) ? $issue['updated'] : null,
            ];
        }

        usort(
            $normalized,
            static function (array $a, array $b): int {
                $updatedCompare = strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));

                if ($updatedCompare !== 0) {
                    return $updatedCompare;
                }

                return strcmp($a['issue_key'], $b['issue_key']);
            },
        );

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $baseFilters
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $user
     * @param  array<int, array<string, mixed>>  $projectMembers
     * @return array<int, array<string, mixed>>
     */
    private function fetchIssuesForCustomField(
        string $apiKey,
        array $baseFilters,
        array $field,
        array $user,
        array $projectMembers = [],
    ): array {
        $fieldId = (int) $field['id'];
        $filterValues = $this->resolveCustomFieldFilterValues($field, $user, $projectMembers);

        if ($filterValues === []) {
            return [];
        }

        return $this->fetchAllIssues($apiKey, array_merge($baseFilters, [
            'customField_'.$fieldId => $filterValues,
        ]));
    }

    /**
     * Run multiple issue-list queries concurrently (paginated).
     *
     * @param  array<int, array<string, mixed>>  $filterSets
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function fetchIssueQueriesConcurrently(string $apiKey, array $filterSets): array
    {
        if ($filterSets === []) {
            return [];
        }

        $baseUrl = rtrim((string) config('backlog.url'), '/').'/api/v2/issues';
        $results = array_fill(0, count($filterSets), []);
        $offsets = array_fill(0, count($filterSets), 0);
        $queue = array_keys($filterSets);
        $concurrency = 8;

        while ($queue !== []) {
            $batchIndices = array_slice($queue, 0, $concurrency);
            $queue = array_slice($queue, $concurrency);

            $responses = Http::pool(function (Pool $pool) use ($batchIndices, $filterSets, $offsets, $apiKey, $baseUrl): void {
                foreach ($batchIndices as $index) {
                    $params = [
                        'apiKey' => $apiKey,
                        'count' => 100,
                        'offset' => $offsets[$index],
                    ];

                    foreach ($filterSets[$index] as $key => $values) {
                        $params[$key] = $values;
                    }

                    $pool->as((string) $index)->get($baseUrl, $params);
                }
            });

            foreach ($batchIndices as $index) {
                $response = $responses[(string) $index] ?? null;

                if (! $response instanceof Response || ! $response->successful()) {
                    continue;
                }

                $page = $response->json() ?? [];

                if (! is_array($page) || $page === []) {
                    continue;
                }

                $results[$index] = array_merge($results[$index], $page);

                if (count($page) === 100) {
                    $offsets[$index] += 100;
                    $queue[] = $index;
                }
            }
        }

        return $results;
    }

    /**
     * List-type custom fields require list item IDs (Value ID), not Backlog user IDs.
     *
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $user
     * @param  array<int, array<string, mixed>>  $projectMembers
     * @return array<int, int>
     */
    private function resolveCustomFieldFilterValues(
        array $field,
        array $user,
        array $projectMembers = [],
    ): array {
        $typeId = (int) ($field['type_id'] ?? 0);
        $items = is_array($field['items'] ?? null) ? $field['items'] : [];

        if (in_array($typeId, [5, 6, 7, 8], true) && $items !== []) {
            $matchedItemIds = $this->matchListItemsToUser($items, $user, $projectMembers);

            if ($matchedItemIds !== []) {
                return $matchedItemIds;
            }
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $user
     * @param  array<int, array<string, mixed>>  $projectMembers
     * @return array<int, int>
     */
    private function matchListItemsToUser(array $items, array $user, array $projectMembers = []): array
    {
        $needles = $this->userMatchNeedles($user, $projectMembers);

        if ($needles === []) {
            return [];
        }

        $matched = [];

        foreach ($items as $item) {
            $itemName = trim((string) ($item['name'] ?? ''));

            if ($itemName === '') {
                continue;
            }

            if ($this->personLabelMatchesNeedles($itemName, $needles)) {
                $matched[] = (int) $item['id'];
            }
        }

        return array_values(array_unique($matched));
    }

    /**
     * @param  array<string, mixed>  $user
     * @param  array<int, array<string, mixed>>  $projectMembers
     * @return array<int, string>
     */
    private function userMatchNeedles(array $user, array $projectMembers = []): array
    {
        $userId = (int) ($user['id'] ?? 0);
        $nulabAccount = is_array($user['nulabAccount'] ?? null) ? $user['nulabAccount'] : [];

        $rawValues = array_filter([
            (string) ($user['name'] ?? ''),
            (string) ($user['userId'] ?? ''),
            (string) ($user['keyword'] ?? ''),
            (string) ($user['mailAddress'] ?? ''),
            (string) ($nulabAccount['name'] ?? ''),
            (string) ($nulabAccount['uniqueId'] ?? ''),
        ]);

        foreach ($projectMembers as $member) {
            if ((int) ($member['id'] ?? 0) !== $userId) {
                continue;
            }

            $rawValues[] = (string) ($member['name'] ?? '');
            $rawValues[] = (string) ($member['user_id'] ?? '');
        }

        $needles = [];

        foreach ($rawValues as $value) {
            foreach ($this->normalizePersonLabels($value) as $label) {
                $needles[] = $label;
            }
        }

        return array_values(array_unique(array_filter($needles)));
    }

    /**
     * @return array<int, string>
     */
    private function normalizePersonLabels(string $value): array
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return [];
        }

        $labels = [mb_strtolower($trimmed)];

        if (str_contains($trimmed, '@')) {
            $labels[] = mb_strtolower(strtok($trimmed, '@') ?: '');
        }

        $labels[] = mb_strtolower(str_replace(['_', '-'], ' ', $trimmed));
        $labels[] = mb_strtolower((string) preg_replace('/[\s_\-]+/u', '', $trimmed));

        $tokens = preg_split('/[\s,]+/u', mb_strtolower($trimmed)) ?: [];

        foreach ($tokens as $token) {
            $token = trim((string) $token);

            if (mb_strlen($token) >= 3) {
                $labels[] = $token;
            }
        }

        return array_values(array_unique(array_filter($labels)));
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function personLabelMatchesNeedles(string $label, array $needles): bool
    {
        foreach ($this->normalizePersonLabels($label) as $candidate) {
            foreach ($needles as $needle) {
                if ($candidate === $needle) {
                    return true;
                }

                if (mb_strlen($needle) >= 3 && (str_contains($candidate, $needle) || str_contains($needle, $candidate))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getMyself(string $apiKey): ?array
    {
        $response = Http::get(
            rtrim((string) config('backlog.url'), '/').'/api/v2/users/myself',
            [
                'apiKey' => $apiKey,
            ],
        );

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['id'])) {
            return null;
        }

        return $payload;
    }

    /**
     * @param  array<string, array<int, int>|string>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function fetchAllIssues(string $apiKey, array $filters): array
    {
        $baseUrl = rtrim((string) config('backlog.url'), '/');
        $offset = 0;
        $allIssues = [];

        do {
            $params = [
                'apiKey' => $apiKey,
                'count' => 100,
                'offset' => $offset,
            ];

            foreach ($filters as $key => $values) {
                $params[$key] = $values;
            }

            $response = Http::get($baseUrl.'/api/v2/issues', $params);

            if (! $response->successful()) {
                break;
            }

            $batch = $response->json() ?? [];

            if (! is_array($batch) || $batch === []) {
                break;
            }

            $allIssues = array_merge($allIssues, $batch);
            $offset += count($batch);
        } while (count($batch) === 100);

        return $allIssues;
    }
}
