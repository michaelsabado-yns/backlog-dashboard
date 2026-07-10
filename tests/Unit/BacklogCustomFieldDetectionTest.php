<?php

namespace Tests\Unit;

use App\Services\BacklogActivityService;
use App\Services\BacklogProjectService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BacklogCustomFieldDetectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['backlog.url' => 'https://example.backlog.com']);
        Cache::flush();
    }

    public function test_project_service_detects_qa_and_sub_qa_fields(): void
    {
        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            if ($path === '/api/v2/projects') {
                return Http::response([
                    [
                        'id' => 1,
                        'projectKey' => 'QA',
                        'name' => 'QA Project',
                        'archived' => false,
                    ],
                ]);
            }

            if ($path === '/api/v2/projects/QA/users') {
                return Http::response([]);
            }

            if ($path === '/api/v2/projects/QA/customFields') {
                return Http::response([
                    ['id' => 10, 'typeId' => 5, 'name' => '担当者', 'items' => []],
                    ['id' => 11, 'typeId' => 5, 'name' => 'サブ担当', 'items' => []],
                    ['id' => 20, 'typeId' => 5, 'name' => 'QA In Charge', 'items' => []],
                    ['id' => 21, 'typeId' => 5, 'name' => 'Sub QA In Charge', 'items' => []],
                ]);
            }

            return Http::response([], 404);
        });

        $projects = (new BacklogProjectService)->getProjectsWithDetails('test-key');

        $this->assertCount(1, $projects);
        $project = $projects[0];

        $this->assertSame('担当者', $project['person_in_charge_field']['name']);
        $this->assertSame('サブ担当', $project['sub_person_in_charge_fields'][0]['name']);
        $this->assertSame('QA In Charge', $project['qa_in_charge_field']['name']);
        $this->assertSame('Sub QA In Charge', $project['sub_qa_in_charge_fields'][0]['name']);
        $this->assertSame('qa_in_charge', $project['qa_in_charge_field']['role']);
        $this->assertSame('sub_qa_in_charge', $project['sub_qa_in_charge_fields'][0]['role']);
    }

    public function test_activity_service_classifies_qa_hour_changes(): void
    {
        $service = new BacklogActivityService;
        $resolve = new \ReflectionMethod($service, 'resolveHoursFieldKind');
        $resolve->setAccessible(true);

        $this->assertSame('actual_hours', $resolve->invoke($service, [
            'field' => 'actualHours',
            'type' => 'standard',
        ]));

        $this->assertSame('sub_actual_hours', $resolve->invoke($service, [
            'field' => 'Sub Actual Hours',
            'type' => 'custom',
        ]));

        $this->assertSame('qa_actual_hours', $resolve->invoke($service, [
            'field' => 'QA Actual Hours',
            'type' => 'custom',
        ]));

        $this->assertSame('qa_actual_hours', $resolve->invoke($service, [
            'field' => 'QA In Charge Actual Hours',
            'type' => 'custom',
        ]));

        $this->assertSame('sub_qa_actual_hours', $resolve->invoke($service, [
            'field' => 'Sub QA Actual Hours',
            'type' => 'custom',
        ]));

        $this->assertSame('sub_qa_actual_hours', $resolve->invoke($service, [
            'field' => 'Sub QA In Charge Actual Hours',
            'type' => 'custom',
        ]));

        $this->assertNull($resolve->invoke($service, [
            'field' => 'Estimate Hours',
            'type' => 'custom',
        ]));
    }
}
