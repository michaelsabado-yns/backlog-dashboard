<?php

namespace App\Services;

class NotificationTransformer
{
  private const REASON_LABELS = [
    1 => 'Assigned to Issue',
    2 => 'Issue Commented',
    3 => 'Issue Created',
    4 => 'Issue Updated',
    5 => 'File Added',
    6 => 'Project User Added',
    9 => 'Other',
    10 => 'Assigned to Pull Request',
    11 => 'Comment Added on Pull Request',
    12 => 'Pull Request Added',
    13 => 'Pull Request Updated',
  ];

  /**
   * @param  array<int, array<string, mixed>>  $notifications
   * @return array<int, array<string, mixed>>
   */
  public function transform(array $notifications): array
  {
    return array_values(array_map(
      fn (array $notification) => $this->transformOne($notification),
      $notifications,
    ));
  }

  /**
   * @param  array<string, mixed>  $notification
   * @return array<string, mixed>
   */
  private function transformOne(array $notification): array
  {
    $issueKey = $notification['issue']['issueKey'] ?? null;

    return [
      'id' => $notification['id'],
      'project' => $notification['project']['name'] ?? 'Unknown',
      'issue_key' => $issueKey,
      'summary' => $notification['issue']['summary'] ?? $this->resolveSummary($notification),
      'sender' => $notification['sender']['name'] ?? 'Unknown',
      'type' => self::REASON_LABELS[$notification['reason'] ?? 0] ?? 'Unknown',
      'content' => $notification['comment']['content'] ?? null,
      'created_at' => $notification['created'] ?? null,
      'backlog_url' => $this->buildBacklogUrl($notification, $issueKey),
    ];
  }

  /**
   * @param  array<string, mixed>  $notification
   */
  private function resolveSummary(array $notification): string
  {
    if (isset($notification['pullRequest']['summary'])) {
      return $notification['pullRequest']['summary'];
    }

    if (isset($notification['project']['name'])) {
      return $notification['project']['name'];
    }

    return '—';
  }

  /**
   * @param  array<string, mixed>  $notification
   */
  private function buildBacklogUrl(array $notification, ?string $issueKey): ?string
  {
    $baseUrl = rtrim((string) config('backlog.url'), '/');

    if ($issueKey !== null) {
      return "{$baseUrl}/view/{$issueKey}";
    }

    $projectKey = $notification['project']['projectKey'] ?? null;

    if ($projectKey !== null) {
      return "{$baseUrl}/projects/{$projectKey}";
    }

    return null;
  }
}
