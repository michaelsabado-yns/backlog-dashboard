<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BoardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['backlog.url' => 'https://example.backlog.com']);
        Cache::flush();
    }

    public function test_board_page_renders(): void
    {
        $this->get('/board')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Board/Index')
                ->has('has_api_key'));
    }

    public function test_board_issues_requires_api_key(): void
    {
        $this->getJson('/board/issues')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Missing Backlog API key.']);
    }

    public function test_board_issues_returns_merged_columns_and_deduped_roles(): void
    {
        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if ($path === '/api/v2/users/myself') {
                return Http::response([
                    'id' => 99,
                    'name' => 'Michael',
                    'userId' => 'michael',
                ]);
            }

            if ($path === '/api/v2/projects') {
                return Http::response([
                    [
                        'id' => 1,
                        'projectKey' => 'APP',
                        'name' => 'App',
                        'archived' => false,
                    ],
                ]);
            }

            if ($path === '/api/v2/projects/APP/users') {
                return Http::response([
                    ['id' => 99, 'userId' => 'michael', 'name' => 'Michael'],
                ]);
            }

            if ($path === '/api/v2/projects/APP/customFields') {
                return Http::response([
                    [
                        'id' => 10,
                        'typeId' => 5,
                        'name' => '担当者',
                        'items' => [
                            ['id' => 100, 'name' => 'Michael'],
                        ],
                    ],
                    [
                        'id' => 30,
                        'typeId' => 5,
                        'name' => 'Reviewer',
                        'items' => [
                            ['id' => 300, 'name' => 'Michael'],
                        ],
                    ],
                ]);
            }

            if ($path === '/api/v2/projects/APP/statuses') {
                return Http::response([
                    ['id' => 1, 'name' => 'Open', 'color' => '#ed8077', 'displayOrder' => 1000],
                    ['id' => 2, 'name' => 'In Progress', 'color' => '#4488c5', 'displayOrder' => 2000],
                ]);
            }

            if ($path === '/api/v2/issues') {
                $assigneeIds = $query['assigneeId'] ?? $query['assigneeId[]'] ?? null;
                $picValues = $query['customField_10'] ?? $query['customField_10[]'] ?? null;
                $reviewerValues = $query['customField_30'] ?? $query['customField_30[]'] ?? null;

                if ($assigneeIds !== null) {
                    return Http::response([
                        [
                            'issueKey' => 'APP-1',
                            'summary' => 'Assignee ticket',
                            'projectId' => 1,
                            'actualHours' => 1,
                            'updated' => '2026-08-05T01:00:00Z',
                            'status' => [
                                'id' => 1,
                                'name' => 'Open',
                                'color' => '#ed8077',
                                'displayOrder' => 1000,
                            ],
                            'assignee' => ['name' => 'Michael'],
                            'priority' => ['name' => 'Normal'],
                        ],
                    ]);
                }

                if ($picValues !== null) {
                    return Http::response([
                        [
                            'issueKey' => 'APP-1',
                            'summary' => 'Assignee ticket',
                            'projectId' => 1,
                            'actualHours' => 1,
                            'updated' => '2026-08-05T01:00:00Z',
                            'status' => [
                                'id' => 1,
                                'name' => 'Open',
                                'color' => '#ed8077',
                                'displayOrder' => 1000,
                            ],
                            'assignee' => ['name' => 'Michael'],
                            'priority' => ['name' => 'Normal'],
                        ],
                    ]);
                }

                if ($reviewerValues !== null) {
                    return Http::response([
                        [
                            'issueKey' => 'APP-2',
                            'summary' => 'Review ticket',
                            'projectId' => 1,
                            'actualHours' => 0,
                            'updated' => '2026-08-05T02:00:00Z',
                            'status' => [
                                'id' => 2,
                                'name' => 'In Progress',
                                'color' => '#4488c5',
                                'displayOrder' => 2000,
                            ],
                            'assignee' => ['name' => 'Other'],
                            'priority' => ['name' => 'High'],
                        ],
                    ]);
                }

                return Http::response([]);
            }

            return Http::response([], 404);
        });

        $response = $this->withHeaders([
            'X-Backlog-Api-Key' => 'test-key',
            'X-Backlog-Project-Ids' => '1',
        ])->getJson('/board/issues');

        $response->assertOk()
            ->assertJsonPath('myself.name', 'Michael')
            ->assertJsonPath('columns.0.name', 'Open')
            ->assertJsonPath('columns.1.name', 'In Progress')
            ->assertJsonCount(2, 'issues');

        $issues = collect($response->json('issues'));
        $app1 = $issues->firstWhere('issue_key', 'APP-1');
        $app2 = $issues->firstWhere('issue_key', 'APP-2');

        $this->assertNotNull($app1);
        $this->assertContains('assignee', $app1['roles']);
        $this->assertContains('person_in_charge', $app1['roles']);
        $this->assertSame(['reviewer'], $app2['roles']);
    }

    public function test_board_issues_force_refresh_bypasses_cache(): void
    {
        $calls = 0;

        Http::fake(function ($request) use (&$calls) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            $calls++;

            if ($path === '/api/v2/users/myself') {
                return Http::response([
                    'id' => 99,
                    'name' => 'Michael',
                    'userId' => 'michael',
                ]);
            }

            if ($path === '/api/v2/projects') {
                return Http::response([
                    ['id' => 1, 'projectKey' => 'APP', 'name' => 'App', 'archived' => false],
                ]);
            }

            if (str_ends_with((string) $path, '/users') || str_ends_with((string) $path, '/customFields')) {
                return Http::response([]);
            }

            if (str_ends_with((string) $path, '/statuses')) {
                return Http::response([
                    ['id' => 1, 'name' => 'Open', 'color' => '#ed8077', 'displayOrder' => 1000],
                ]);
            }

            if ($path === '/api/v2/issues') {
                return Http::response([]);
            }

            return Http::response([], 404);
        });

        $headers = [
            'X-Backlog-Api-Key' => 'test-key',
            'X-Backlog-Project-Ids' => '1',
        ];

        $this->withHeaders($headers)->getJson('/board/issues')->assertOk();
        $callsAfterFirst = $calls;

        $this->withHeaders($headers)->getJson('/board/issues')->assertOk()
            ->assertJsonPath('from_cache', true);
        $this->assertSame($callsAfterFirst, $calls);

        $this->withHeaders($headers)->getJson('/board/issues?force=1')->assertOk()
            ->assertJsonPath('from_cache', false);
        $this->assertGreaterThan($callsAfterFirst, $calls);

        $this->withHeaders($headers)->getJson('/board/issues?force=true')->assertOk()
            ->assertJsonPath('from_cache', false);
    }
}
