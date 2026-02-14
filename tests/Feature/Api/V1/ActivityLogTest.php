<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_activity_logs()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create some activity
        activity()
            ->performedOn($user)
            ->causedBy($user)
            ->log('User created');

        $response = $this->getJson(route('api.v1.activity-logs.index'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'log_name',
                        'description',
                        'subject_type',
                        'subject_id',
                        'causer_type',
                        'causer_id',
                        'properties',
                        'created_at',
                        'updated_at',
                        'is_mine', // New field
                    ],
                ],
                'meta',
                'links',
            ]);

        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_can_distinguish_mine_vs_others()
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        // Activity by me
        activity()->causedBy($me)->log('My Activity');

        // Activity by other
        activity()->causedBy($other)->log('Other Activity');

        $this->actingAs($me);

        // 1. Check Index - Should show both, with correct is_mine flag
        $response = $this->getJson(route('api.v1.activity-logs.index'));
        $response->assertStatus(200);

        $data = collect($response->json('data'));

        $myLog = $data->firstWhere('description', 'My Activity');
        $otherLog = $data->firstWhere('description', 'Other Activity');

        $this->assertTrue($myLog['is_mine']);
        $this->assertFalse($otherLog['is_mine']);

        // 2. Check Mine Endpoint - Should only show mine
        $responseMine = $this->getJson(route('api.v1.activity-logs.mine'));
        $responseMine->assertStatus(200);

        $this->assertCount(1, $responseMine->json('data'));
        $this->assertEquals('My Activity', $responseMine->json('data.0.description'));
    }
}
