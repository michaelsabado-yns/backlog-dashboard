<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Service for handling Common Task activities such as Code Reviews and Created Tickets.
 */
class BacklogCommonTaskService
{
    public function __construct(
        private readonly BacklogIssueService $issueService,
        private readonly BacklogActivityService $activityService,
    ) {}

    /**
     * Fetch issues updated or reviewed/created on a specific date for a user.
     *
     * @param  array<int, int>|null  $projectIds
     * @param  array<string, mixed>  $trackedUser
     * @return array<int, array<string, mixed>>
     */
    public function getIssuesUpdatedOnDateForUser(
        string $apiKey,
        ?array $projectIds,
        string $date,
        ?string $timezone,
        array $trackedUser,
    ): array {
        $trimmedApiKey = trim($apiKey);

        if ($trimmedApiKey === '') {
            return [];
        }

        $userId = (int) ($trackedUser['id'] ?? 0);
        $userName = mb_strtolower(trim($trackedUser['name'] ?? ''));
        $tz = $timezone ?: (string) config('app.timezone', 'UTC');
        $targetDateStr = substr($date, 0, 10);

        $matchedKeys = [];

        $matchedIssues = [];

        // 1. Inspect user activity log for the target date
        if ($userId > 0) {
            $activities = $this->activityService->getUserActivitiesForDate($trimmedApiKey, $userId, $date, $tz);

            foreach ($activities as $activity) {
                $projectId = (int) ($activity['project']['id'] ?? 0);
                if (! empty($projectIds) && ! in_array($projectId, $projectIds, true)) {
                    continue;
                }

                $issueKey = $this->extractIssueKeyFromActivity($activity);
                if ($issueKey === null) {
                    continue;
                }


                $isReviewer = $this->isActivityCodeReview($activity, $userId, $userName);
                $isCreator = ($activity['type'] ?? 0) === 1;

                if ($isReviewer || $isCreator) {
                    $matchedKeys[$issueKey] ??= ['is_code_review' => false, 'is_created_ticket' => false];
                    if ($isReviewer) {
                        $matchedKeys[$issueKey]['is_code_review'] = true;
                    }
                    if ($isCreator) {
                        $matchedKeys[$issueKey]['is_created_ticket'] = true;
                    }
                }
            }
        }

        // 2. Also query issues updated on target date as fallback
        $filters = [
            'updatedSince' => $date,
            'updatedUntil' => $date,
        ];

        if (! empty($projectIds)) {
            $filters['projectId'] = $projectIds;
        }

        $updatedIssues = $this->issueService->fetchAllIssues($trimmedApiKey, $filters);

        foreach ($updatedIssues as $issue) {
            $issueKey = $issue['issueKey'] ?? null;
            if (! is_string($issueKey) || $issueKey === '') {
                continue;
            }

            $matchedIssues[$issueKey] = $issue;

            $createdStr = $issue['created'] ?? '';
            if ($createdStr !== '') {
                try {
                    $createdLocal = Carbon::parse($createdStr)->timezone($tz)->toDateString();
                } catch (\Exception $e) {
                    $createdLocal = substr($createdStr, 0, 10);
                }
            } else {
                $createdLocal = '';
            }

            $isCreatedToday = $createdLocal === $targetDateStr;
            $isCreator = isset($issue['createdUser']['id']) && (int) $issue['createdUser']['id'] === $userId && $isCreatedToday;
            $isReviewer = $this->checkIssueHasReviewer($issue, $userId, $userName);

            if ($isCreator || $isReviewer) {
                $matchedKeys[$issueKey] ??= ['is_code_review' => false, 'is_created_ticket' => false];
                if ($isReviewer) {
                    $matchedKeys[$issueKey]['is_code_review'] = true;
                }
                if ($isCreator) {
                    $matchedKeys[$issueKey]['is_created_ticket'] = true;
                }
            }
        }

        if (empty($matchedKeys)) {
            return [];
        }

        // 3. Fetch full issue details for matched issue keys via getIssueByKey
        foreach (array_keys($matchedKeys) as $issueKey) {
            if (! isset($matchedIssues[$issueKey])) {
                $fetched = $this->issueService->getIssueByKey($trimmedApiKey, $issueKey);
                if ($fetched !== null) {
                    $matchedIssues[$issueKey] = $fetched;
                }
            }
        }

        $matched = [];

        foreach ($matchedKeys as $issueKey => $flags) {
            $issue = $matchedIssues[$issueKey] ?? null;
            if ($issue === null) {
                continue;
            }

            $status = is_array($issue['status'] ?? null) ? $issue['status'] : [];
            $issueType = is_array($issue['issueType'] ?? null) ? $issue['issueType'] : [];
            $createdUser = is_array($issue['createdUser'] ?? null) ? $issue['createdUser'] : [];
            $project = is_array($issue['project'] ?? null) ? $issue['project'] : [];

            $matched[] = [
                'issue_key' => $issueKey,
                'summary' => $issue['summary'] ?? '',
                'worked_hours' => 0.0,
                'previous_hours' => 0.0,
                'current_hours' => 0.0,
                'project_key' => $project['projectKey'] ?? null,
                'project_name' => $project['name'] ?? null,
                'updated_at' => $issue['updated'] ?? null,
                'issue_status' => is_string($status['name'] ?? null) ? $status['name'] : null,
                'issue_status_color' => is_string($status['color'] ?? null) ? $status['color'] : null,
                'issue_type' => is_string($issueType['name'] ?? null) ? $issueType['name'] : null,
                'created_user_id' => isset($createdUser['id']) ? (int) $createdUser['id'] : null,
                'created_user_name' => is_string($createdUser['name'] ?? null) ? $createdUser['name'] : null,
                'custom_fields' => is_array($issue['customFields'] ?? null) ? $issue['customFields'] : [],
                'is_code_review' => (bool) ($flags['is_code_review'] ?? false),
                'is_created_ticket' => (bool) ($flags['is_created_ticket'] ?? false),
            ];
        }

        return $matched;
    }

    /**
     * Extract the full issue key (e.g. PROJECT-123) from a raw activity payload.
     *
     * @param  array<string, mixed>  $activity
     */
    private function extractIssueKeyFromActivity(array $activity): ?string
    {
        $projectKey = $activity['project']['projectKey'] ?? null;
        $keyId = $activity['content']['key_id'] ?? null;

        if (is_string($projectKey) && $projectKey !== '' && $keyId !== null) {
            return $projectKey.'-'.$keyId;
        }

        return null;
    }

    /**
     * Determine whether an activity change indicates code review activity for a user.
     *
     * @param  array<string, mixed>  $activity
     */
    private function isActivityCodeReview(array $activity, int $userId, string $userName): bool
    {
        $changes = is_array($activity['content']['changes'] ?? null) ? $activity['content']['changes'] : [];

        foreach ($changes as $change) {
            $field = mb_strtolower(trim($change['field'] ?? ''));
            $name = mb_strtolower(trim($change['name'] ?? ''));
            $newValue = mb_strtolower(trim(is_string($change['new_value'] ?? null) ? $change['new_value'] : (is_scalar($change['new_value'] ?? null) ? (string) $change['new_value'] : '')));

            if ($field === 'status' || $field === 'status_id' || $field === 'statusid' || str_contains($name, 'status') || str_contains($name, '状態')) {
                if ($this->isCodeReviewStatus($newValue)) {
                    return true;
                }
            }

            if (
                in_array($field, ['reviewer', 'code reviewer', 'レビュアー', 'レビュワ'], true) ||
                in_array($name, ['reviewer', 'code reviewer', 'レビュアー', 'レビュワ'], true) ||
                str_contains($name, 'reviewer') ||
                str_contains($name, 'レビュアー')
            ) {
                // Only count as code review if the tracked user is actually set as the reviewer.
                if ($newValue !== '' && (str_contains($newValue, (string) $userId) || ($userName !== '' && str_contains($newValue, $userName)))) {
                    return true;
                }
            }
        }

        $comment = is_array($activity['content']['comment'] ?? null) ? ($activity['content']['comment']['content'] ?? '') : '';
        if (is_string($comment) && $comment !== '') {
            $commentLower = mb_strtolower($comment);
            if (
                str_contains($commentLower, 'ready for testing') ||
                str_contains($commentLower, 'merged to develop') ||
                str_contains($commentLower, 'code review')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a raw status string matches generic code review status patterns
     * (e.g. ready for testing, resolved, closed, review, testing).
     */
    private function isCodeReviewStatus(string $rawStatus): bool
    {
        $normalized = mb_strtolower(trim($rawStatus));

        if ($normalized === '') {
            return false;
        }

        $patterns = [
            'ready for testing',
            'ready for test',
            'resolved',
            'closed',
            'review',
            'testing',
            '検証',
            'レビュー',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether an issue custom fields assign the tracked user as a reviewer.
     *
     * @param  array<string, mixed>  $issue
     */
    private function checkIssueHasReviewer(array $issue, int $userId, string $userName): bool
    {
        foreach ($issue['customFields'] ?? [] as $field) {
            $fieldName = mb_strtolower(trim($field['name'] ?? ''));
            if (in_array($fieldName, ['reviewer', 'code reviewer', 'レビュアー', 'レビュワ'], true)) {
                $value = $field['value'] ?? null;
                if (is_array($value)) {
                    $json = json_encode($value);
                    if (str_contains($json, '"id":'.$userId) || str_contains($json, '"id":"'.$userId.'"')) {
                        return true;
                    }
                    if ($userName !== '' && str_contains(mb_strtolower($json), $userName)) {
                        return true;
                    }
                } else {
                    $strValue = (string) $value;
                    if ($strValue === (string) $userId) {
                        return true;
                    }
                    if ($userName !== '' && str_contains(mb_strtolower($strValue), $userName)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
