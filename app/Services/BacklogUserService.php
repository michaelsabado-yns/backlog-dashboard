<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BacklogUserService
{
    /**
     * @return array{id: int, name: string, user_id: string}|null
     */
    public function getMyself(string $apiKey): ?array
    {
        $trimmedApiKey = trim($apiKey);

        if ($trimmedApiKey === '') {
            return null;
        }

        $response = Http::get(
            rtrim((string) config('backlog.url'), '/').'/api/v2/users/myself',
            ['apiKey' => $trimmedApiKey],
        );

        if (! $response->successful()) {
            return null;
        }

        $user = $response->json();

        if (! is_array($user) || ! isset($user['id'])) {
            return null;
        }

        return $this->normalizeUser($user);
    }

    /**
     * @param  array<int, int>|null  $projectIds
     * @return array<int, array{id: int, name: string, user_id: string}>
     */
    public function getBrowsableUsers(
        string $apiKey,
        BacklogProjectService $projectService,
        ?array $projectIds = null,
    ): array {
        $trimmedApiKey = trim($apiKey);

        if ($trimmedApiKey === '') {
            return [];
        }

        $projects = $projectService->getProjectMappings($trimmedApiKey, $projectIds);
        $usersById = [];

        foreach ($projects as $project) {
            if (($project['archived'] ?? false) === true) {
                continue;
            }

            foreach ($project['members'] ?? [] as $member) {
                $normalized = $this->normalizeUser($member);

                if ($normalized === null) {
                    continue;
                }

                $usersById[$normalized['id']] = $normalized;
            }
        }

        $users = array_values($usersById);

        usort($users, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $users;
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array{id: int, name: string, user_id: string}|null
     */
    private function normalizeUser(array $user): ?array
    {
        if (! isset($user['id'])) {
            return null;
        }

        $id = (int) $user['id'];

        if ($id <= 0) {
            return null;
        }

        return [
            'id' => $id,
            'name' => is_string($user['name'] ?? null) ? $user['name'] : '',
            'user_id' => is_string($user['userId'] ?? ($user['user_id'] ?? null))
                ? ($user['userId'] ?? $user['user_id'])
                : '',
        ];
    }
}
