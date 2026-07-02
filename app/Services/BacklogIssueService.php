<?php

namespace App\Services;

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
     * @param  array<int, int>|null  $projectIds
     * @return array<int, array{issue_key: string, summary: string, actual_hours: float, backlog_url: string, updated_at: string|null}>
     */
    public function getMyIssues(string $apiKey, ?array $projectIds = null, ?string $updatedDate = null): array
    {
        $trimmedApiKey = trim($apiKey);

        if ($trimmedApiKey === '') {
            return [];
        }

        $user = $this->getMyself($trimmedApiKey);

        if ($user === null) {
            return [];
        }

        $projects = $this->projectService->getProjectMappings($trimmedApiKey, $projectIds);

        if ($projectIds !== null && $projectIds === []) {
            return [];
        }

        if ($projects === []) {
            return [];
        }

        $issues = [];

        foreach ($projects as $project) {
            if (($project['archived'] ?? false) === true) {
                continue;
            }

            $projectId = (int) $project['id'];
            $baseFilters = ['projectId' => [$projectId]];

            if ($updatedDate !== null) {
                $baseFilters['updatedSince'] = $updatedDate;
                $baseFilters['updatedUntil'] = $updatedDate;
            }

            $personField = $project['person_in_charge_field'] ?? null;

            if (is_array($personField) && isset($personField['id'])) {
                $issues = array_merge(
                    $issues,
                    $this->fetchIssuesForCustomField(
                        $trimmedApiKey,
                        $baseFilters,
                        $personField,
                        $user,
                        $project['members'] ?? [],
                    ),
                );
            }

            foreach ($project['sub_person_in_charge_fields'] ?? [] as $subField) {
                if (! is_array($subField) || ! isset($subField['id'])) {
                    continue;
                }

                $issues = array_merge(
                    $issues,
                    $this->fetchIssuesForCustomField(
                        $trimmedApiKey,
                        $baseFilters,
                        $subField,
                        $user,
                        $project['members'] ?? [],
                    ),
                );
            }
        }

        return $this->normalizeIssues($issues);
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
        $issues = [];

        foreach ($filterValues as $value) {
            $issues = array_merge(
                $issues,
                $this->fetchAllIssues($apiKey, array_merge($baseFilters, [
                    'customField_'.$fieldId => [$value],
                ])),
            );
        }

        return $issues;
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
    private function fetchAllIssues(string $apiKey, array $filters): array
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

    /**
     * @param  array<int, array<string, mixed>>  $issues
     * @return array<int, array{issue_key: string, summary: string, actual_hours: float, backlog_url: string, updated_at: string|null}>
     */
    private function normalizeIssues(array $issues): array
    {
        $baseUrl = rtrim((string) config('backlog.url'), '/');
        $byKey = [];

        foreach ($issues as $issue) {
            $issueKey = $issue['issueKey'] ?? null;

            if (! is_string($issueKey) || $issueKey === '') {
                continue;
            }

            $byKey[$issueKey] = [
                'issue_key' => $issueKey,
                'summary' => is_string($issue['summary'] ?? null) ? $issue['summary'] : '',
                'actual_hours' => isset($issue['actualHours']) ? (float) $issue['actualHours'] : 0.0,
                'backlog_url' => $baseUrl.'/view/'.$issueKey,
                'updated_at' => is_string($issue['updated'] ?? null) ? $issue['updated'] : null,
            ];
        }

        return array_values($byKey);
    }
}
