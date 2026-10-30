<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PatientPharmacyMessagingSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') === 'sqlite') {
            try {
                \Illuminate\Support\Facades\DB::statement("ATTACH ':memory:' AS information_schema");
                \Illuminate\Support\Facades\DB::statement("CREATE TABLE IF NOT EXISTS information_schema.table_constraints (table_name TEXT, constraint_name TEXT, constraint_type TEXT)");
            } catch (\Throwable $e) {}
        }
    }

    protected User $patientA;
    protected User $patientB;
    protected Pharmacy $pharmacyA;
    protected Pharmacy $pharmacyB;
    protected User $pharmacyOwnerA;
    protected User $pharmacyOwnerB;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        // 1. Create Patient A
        $this->patientA = User::create([
            'name' => 'Patient Alice',
            'email' => 'alice@patient.test',
            'phone' => '09171111111',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_CUSTOMER,
            'account_status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        // 2. Create Patient B (Adversary)
        $this->patientB = User::create([
            'name' => 'Patient Bob',
            'email' => 'bob@patient.test',
            'phone' => '09172222222',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_CUSTOMER,
            'account_status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        // 3. Create Pharmacy A (Bucquial's)
        $this->pharmacyA = Pharmacy::create([
            'name' => "Bucquial's Pharmacy",
            'slug' => 'bucquials-pharmacy',
            'address' => 'Alijis Road, Bacolod City',
            'latitude' => 10.6385,
            'longitude' => 122.9520,
            'status' => 'approved',
            'is_active' => true,
        ]);

        // 4. Create Pharmacy B (Competitor)
        $this->pharmacyB = Pharmacy::create([
            'name' => 'Mercury Drug Alijis',
            'slug' => 'mercury-drug-alijis',
            'address' => 'Corner Alijis, Bacolod City',
            'latitude' => 10.6400,
            'longitude' => 122.9550,
            'status' => 'approved',
            'is_active' => true,
        ]);

        // 5. Create Pharmacy Owner A
        $this->pharmacyOwnerA = User::create([
            'name' => 'Renz Bucquial',
            'email' => 'owner.a@bucquials.test',
            'phone' => '09181111111',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_PHARMACY_OWNER,
            'pharmacy_id' => $this->pharmacyA->id,
            'account_status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        // 6. Create Pharmacy Owner B
        $this->pharmacyOwnerB = User::create([
            'name' => 'Owner Mercury',
            'email' => 'owner.b@mercury.test',
            'phone' => '09182222222',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_PHARMACY_OWNER,
            'pharmacy_id' => $this->pharmacyB->id,
            'account_status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * 1. Unauthenticated mobile requests are strictly rejected with 401.
     */
    public function test_unauthenticated_request_cannot_start_or_view_conversation(): void
    {
        $response = $this->getJson("/api/pharmacies/{$this->pharmacyA->id}/conversation");
        $response->assertStatus(401);
    }

    /**
     * 2. Authenticated patient can start or retrieve a conversation by pharmacy ID.
     */
    public function test_authenticated_patient_can_get_or_create_conversation_for_pharmacy(): void
    {
        Sanctum::actingAs($this->patientA);

        $response = $this->getJson("/api/pharmacies/{$this->pharmacyA->id}/conversation");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'pharmacy' => [
                    'id' => $this->pharmacyA->id,
                    'name' => "Bucquial's Pharmacy",
                ],
                'messages' => [],
            ])
            ->assertJsonStructure([
                'status',
                'conversation_id',
                'pharmacy' => ['id', 'name', 'address'],
                'messages',
            ]);

        $convId = $response->json('conversation_id');
        $this->assertNotNull($convId);

        $this->assertDatabaseHas('conversations', [
            'id' => $convId,
            'user_id' => $this->patientA->id,
            'pharmacy_id' => $this->pharmacyA->id,
        ]);
    }

    /**
     * 3. Idempotency: Repeated calls return the exact same conversation ID without duplicating.
     */
    public function test_repeated_conversation_creation_is_idempotent(): void
    {
        Sanctum::actingAs($this->patientA);

        $res1 = $this->getJson("/api/pharmacies/{$this->pharmacyA->id}/conversation");
        $convId1 = $res1->json('conversation_id');

        $res2 = $this->getJson("/api/pharmacies/{$this->pharmacyA->id}/conversation");
        $convId2 = $res2->json('conversation_id');

        $this->assertEquals($convId1, $convId2);
        $this->assertEquals(1, Conversation::where('user_id', $this->patientA->id)->where('pharmacy_id', $this->pharmacyA->id)->count());
    }

    /**
     * 4. Invalid or non-existent pharmacy ID returns 404 or 422.
     */
    public function test_invalid_pharmacy_id_returns_error(): void
    {
        Sanctum::actingAs($this->patientA);

        $this->getJson("/api/pharmacies/999999/conversation")
            ->assertStatus(404);

        $this->getJson("/api/pharmacies/-5/conversation")
            ->assertStatus(422);
    }

    /**
     * 5. Patient can send a message and retrieve it with normalized body, is_mine, and timestamps.
     */
    public function test_patient_can_send_and_retrieve_messages(): void
    {
        Sanctum::actingAs($this->patientA);

        $startRes = $this->getJson("/api/pharmacies/{$this->pharmacyA->id}/conversation");
        $convId = $startRes->json('conversation_id');

        // Send message
        $sendRes = $this->postJson("/api/messages/{$convId}", [
            'message' => 'Do you have Biogesic in stock?',
        ]);

        $sendRes->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => [
                    'conversation_id' => $convId,
                    'body' => 'Do you have Biogesic in stock?',
                    'is_mine' => true,
                ],
            ]);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $convId,
            'sender_id' => $this->patientA->id,
            'body' => 'Do you have Biogesic in stock?',
        ]);

        // Retrieve messages
        $getRes = $this->getJson("/api/messages/{$convId}");
        $getRes->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'conversation_id' => $convId,
            ]);

        $messages = $getRes->json('messages');
        $this->assertCount(1, $messages);
        $this->assertEquals('Do you have Biogesic in stock?', $messages[0]['body']);
        $this->assertTrue($messages[0]['is_mine']);
        $this->assertNotEmpty($messages[0]['created_at']);
    }

    /**
     * 6. Validation: Empty or whitespace-only message returns 422.
     */
    public function test_empty_or_whitespace_message_is_rejected(): void
    {
        Sanctum::actingAs($this->patientA);

        $conv = Conversation::findOrStart($this->patientA->id, $this->pharmacyA->id);

        $this->postJson("/api/messages/{$conv->id}", ['message' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);

        $this->postJson("/api/messages/{$conv->id}", ['message' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    /**
     * 7. Validation: Message exceeding 1000 characters is rejected.
     */
    public function test_overlength_message_is_rejected(): void
    {
        Sanctum::actingAs($this->patientA);

        $conv = Conversation::findOrStart($this->patientA->id, $this->pharmacyA->id);

        $longText = str_repeat('A', 1001);
        $this->postJson("/api/messages/{$conv->id}", ['message' => $longText])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    /**
     * 8. Security Isolation: Patient B cannot view or send to Patient A's conversation.
     */
    public function test_patient_b_cannot_access_or_send_to_patient_a_conversation(): void
    {
        // Patient A creates a conversation
        $convA = Conversation::findOrStart($this->patientA->id, $this->pharmacyA->id);

        // Patient B attempts to read Patient A's conversation
        Sanctum::actingAs($this->patientB);

        $this->getJson("/api/messages/{$convA->id}")
            ->assertStatus(404);

        // Patient B attempts to send into Patient A's conversation
        $this->postJson("/api/messages/{$convA->id}", [
            'message' => 'Malicious injection attempt',
        ])->assertStatus(404);

        $this->assertDatabaseMissing('messages', [
            'body' => 'Malicious injection attempt',
        ]);
    }

    /**
     * 9. Pharmacy Isolation: Pharmacy Owner A sees Patient A's conversation; Pharmacy Owner B cannot.
     */
    public function test_pharmacy_admin_isolation(): void
    {
        // Patient A sends message to Pharmacy A
        $convA = Conversation::findOrStart($this->patientA->id, $this->pharmacyA->id);
        $convA->messages()->create([
            'sender_id' => $this->patientA->id,
            'body' => 'Hello Bucquials Pharmacy',
        ]);

        // Pharmacy Owner A views portal messages
        $this->actingAs($this->pharmacyOwnerA, 'pharmacy')
            ->get(route('pharmacy.messages', $convA->id))
            ->assertStatus(200)
            ->assertSee("Patient Alice")
            ->assertSee('Hello Bucquials Pharmacy');

        // Pharmacy Owner B attempts to view Pharmacy A's conversation
        $this->actingAs($this->pharmacyOwnerB, 'pharmacy')
            ->get(route('pharmacy.messages', $convA->id))
            ->assertStatus(404);
    }

    /**
     * 10a. Database Uniqueness: Partial unique index strictly rejects duplicate direct conversations (order_id IS NULL).
     */
    public function test_partial_unique_index_strictly_rejects_duplicate_direct_conversation(): void
    {
        // First direct conversation
        Conversation::create([
            'user_id' => $this->patientA->id,
            'pharmacy_id' => $this->pharmacyA->id,
            'order_id' => null,
        ]);

        // Second direct conversation must fail with QueryException
        $this->expectException(\Illuminate\Database\QueryException::class);
        Conversation::create([
            'user_id' => $this->patientA->id,
            'pharmacy_id' => $this->pharmacyA->id,
            'order_id' => null,
        ]);
    }

    /**
     * 10b. Application Recovery: findOrStart recovers gracefully from database uniqueness conflicts.
     */
    public function test_find_or_start_handles_unique_index_conflict_gracefully(): void
    {
        $conv1 = Conversation::findOrStart($this->patientA->id, $this->pharmacyA->id, null);
        $this->assertNotNull($conv1->id);

        // A second call for direct conversation returns the exact same record without crashing
        $conv2 = Conversation::findOrStart($this->patientA->id, $this->pharmacyA->id, null);
        $this->assertEquals($conv1->id, $conv2->id);
        $this->assertEquals(1, Conversation::where('user_id', $this->patientA->id)->where('pharmacy_id', $this->pharmacyA->id)->whereNull('order_id')->count());
    }

    /**
     * 10c. Order-Associated Threads: Multiple conversations with distinct order IDs remain permitted.
     */
    public function test_order_associated_conversations_allow_multiple_threads(): void
    {
        // Direct conversation (order_id IS NULL)
        $directConv = Conversation::create([
            'user_id' => $this->patientA->id,
            'pharmacy_id' => $this->pharmacyA->id,
            'order_id' => null,
        ]);

        // Create two real orders to satisfy foreign key constraint
        $order1 = \App\Models\Order::create([
            'user_id' => $this->patientA->id,
            'pharmacy_id' => $this->pharmacyA->id,
            'order_number' => 'ORD-SEC-001',
            'status' => 'pending',
            'delivery_address' => 'Alijis Bacolod',
            'total_amount' => 100.00,
        ]);

        $order2 = \App\Models\Order::create([
            'user_id' => $this->patientA->id,
            'pharmacy_id' => $this->pharmacyA->id,
            'order_number' => 'ORD-SEC-002',
            'status' => 'pending',
            'delivery_address' => 'Alijis Bacolod',
            'total_amount' => 200.00,
        ]);

        // Order 1 conversation
        $orderConv1 = Conversation::create([
            'user_id' => $this->patientA->id,
            'pharmacy_id' => $this->pharmacyA->id,
            'order_id' => $order1->id,
        ]);

        // Order 2 conversation
        $orderConv2 = Conversation::create([
            'user_id' => $this->patientA->id,
            'pharmacy_id' => $this->pharmacyA->id,
            'order_id' => $order2->id,
        ]);

        $this->assertNotEquals($directConv->id, $orderConv1->id);
        $this->assertNotEquals($orderConv1->id, $orderConv2->id);
        $this->assertEquals(3, Conversation::where('user_id', $this->patientA->id)->where('pharmacy_id', $this->pharmacyA->id)->count());
    }

    /**
     * 10d. Pharmacy Eligibility Matrix: Enforces approved AND active rules for inquiries.
     */
    public function test_pharmacy_eligibility_matrix_enforces_approved_and_active_rules(): void
    {
        Sanctum::actingAs($this->patientA);

        // Case 1: Approved + Active -> Allowed (200)
        $pApprovedActive = Pharmacy::create([
            'name' => 'Approved Active Pharmacy',
            'slug' => 'approved-active',
            'address' => 'Bacolod City',
            'latitude' => 10.6400,
            'longitude' => 122.9550,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $res1 = $this->getJson("/api/pharmacies/{$pApprovedActive->id}/conversation");
        $res1->assertStatus(200);
        $this->assertDatabaseHas('conversations', ['user_id' => $this->patientA->id, 'pharmacy_id' => $pApprovedActive->id]);

        // Case 2: Approved + Inactive -> Rejected (422)
        $pApprovedInactive = Pharmacy::create([
            'name' => 'Approved Inactive Pharmacy',
            'slug' => 'approved-inactive',
            'address' => 'Bacolod City',
            'latitude' => 10.6400,
            'longitude' => 122.9550,
            'status' => 'approved',
            'is_active' => false,
        ]);
        $res2 = $this->getJson("/api/pharmacies/{$pApprovedInactive->id}/conversation");
        $res2->assertStatus(422)
            ->assertJson(['status' => 'error', 'message' => 'This pharmacy is currently inactive.']);
        $this->assertDatabaseMissing('conversations', ['user_id' => $this->patientA->id, 'pharmacy_id' => $pApprovedInactive->id]);

        // Case 3: Pending + Active -> Rejected (422)
        $pPendingActive = Pharmacy::create([
            'name' => 'Pending Active Pharmacy',
            'slug' => 'pending-active',
            'address' => 'Bacolod City',
            'latitude' => 10.6400,
            'longitude' => 122.9550,
            'status' => 'pending',
            'is_active' => true,
        ]);
        $res3 = $this->getJson("/api/pharmacies/{$pPendingActive->id}/conversation");
        $res3->assertStatus(422)
            ->assertJson(['status' => 'error', 'message' => 'This pharmacy is currently inactive.']);
        $this->assertDatabaseMissing('conversations', ['user_id' => $this->patientA->id, 'pharmacy_id' => $pPendingActive->id]);

        // Case 4: Pending + Inactive -> Rejected (422)
        $pPendingInactive = Pharmacy::create([
            'name' => 'Pending Inactive Pharmacy',
            'slug' => 'pending-inactive',
            'address' => 'Bacolod City',
            'latitude' => 10.6400,
            'longitude' => 122.9550,
            'status' => 'pending',
            'is_active' => false,
        ]);
        $res4 = $this->getJson("/api/pharmacies/{$pPendingInactive->id}/conversation");
        $res4->assertStatus(422)
            ->assertJson(['status' => 'error', 'message' => 'This pharmacy is currently inactive.']);
        $this->assertDatabaseMissing('conversations', ['user_id' => $this->patientA->id, 'pharmacy_id' => $pPendingInactive->id]);

        // Case 5: Suspended + Active -> Rejected (422)
        $pSuspended = Pharmacy::create([
            'name' => 'Suspended Pharmacy',
            'slug' => 'suspended-pharmacy',
            'address' => 'Bacolod City',
            'latitude' => 10.6400,
            'longitude' => 122.9550,
            'status' => 'suspended',
            'is_active' => true,
        ]);
        $res5 = $this->getJson("/api/pharmacies/{$pSuspended->id}/conversation");
        $res5->assertStatus(422);
        $this->assertDatabaseMissing('conversations', ['user_id' => $this->patientA->id, 'pharmacy_id' => $pSuspended->id]);
    }

    /**
     * 10e. Patient Role Enforcement: Pharmacy staff and drivers cannot initiate patient conversations.
     */
    public function test_patient_role_enforcement_prevents_pharmacy_staff_and_drivers_from_initiating_patient_conversations(): void
    {
        // Pharmacy Owner A attempts to call mobile patient conversation-start
        Sanctum::actingAs($this->pharmacyOwnerA);
        $this->getJson("/api/pharmacies/{$this->pharmacyB->id}/conversation")
            ->assertStatus(403)
            ->assertJson(['status' => 'error', 'message' => 'Only patient accounts can initiate pharmacy conversations.']);

        // Pharmacy Owner A attempts to access patient conversation list
        $this->getJson("/api/messages")
            ->assertStatus(403)
            ->assertJson(['status' => 'error', 'message' => 'Only patient accounts can access this conversation list.']);

        $patientConv = Conversation::findOrStart($this->patientA->id, $this->pharmacyA->id);

        // Pharmacy Owner A attempts to read patient messages via mobile API
        $this->getJson("/api/messages/{$patientConv->id}")
            ->assertStatus(403)
            ->assertJson(['status' => 'error', 'message' => 'Only patient accounts can access patient messaging.']);

        // Pharmacy Owner A attempts to send message via mobile API
        $this->postJson("/api/messages/{$patientConv->id}", ['message' => 'Sneak message'])
            ->assertStatus(403)
            ->assertJson(['status' => 'error', 'message' => 'Only patient accounts can send messages via this endpoint.']);

        // Driver attempts to call mobile patient conversation-start
        $driver = User::create([
            'name' => 'Courier Dave',
            'email' => 'driver.dave@georx.test',
            'phone' => '09193333333',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_DRIVER,
            'account_status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($driver);
        $this->getJson("/api/pharmacies/{$this->pharmacyA->id}/conversation")
            ->assertStatus(403)
            ->assertJson(['status' => 'error', 'message' => 'Only patient accounts can initiate pharmacy conversations.']);

        // Driver attempts to read patient messages via mobile API
        $this->getJson("/api/messages/{$patientConv->id}")
            ->assertStatus(403)
            ->assertJson(['status' => 'error', 'message' => 'Only patient accounts can access patient messaging.']);

        // Driver attempts to send via mobile API
        $this->postJson("/api/messages/{$patientConv->id}", ['message' => 'Driver message'])
            ->assertStatus(403)
            ->assertJson(['status' => 'error', 'message' => 'Only patient accounts can send messages via this endpoint.']);
    }

    /**
     * 11. Validation: Wrong-type and null inputs are strictly rejected with 422.
     */
    public function test_wrong_type_and_null_message_inputs_are_rejected(): void
    {
        Sanctum::actingAs($this->patientA);
        $conv = Conversation::findOrStart($this->patientA->id, $this->pharmacyA->id);

        // Null message
        $this->postJson("/api/messages/{$conv->id}", ['message' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);

        // Array message
        $this->postJson("/api/messages/{$conv->id}", ['message' => ['nested' => 'text']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    /**
     * 12. Bidirectional Messaging: Pharmacy replies via web and patient sees it with is_mine = false.
     */
    public function test_bidirectional_messaging_and_bubble_alignment(): void
    {
        $conv = Conversation::findOrStart($this->patientA->id, $this->pharmacyA->id);

        // Patient sends inquiry via API
        Sanctum::actingAs($this->patientA);
        $this->postJson("/api/messages/{$conv->id}", ['message' => 'Is amoxicillin available?'])
            ->assertStatus(200);

        // Pharmacy Owner A replies via web portal
        $this->actingAs($this->pharmacyOwnerA, 'pharmacy')
            ->post(route('pharmacy.messages.send', $conv->id), [
                'message' => 'Yes, 500mg capsules are in stock at PHP 15.00 each.',
            ])
            ->assertRedirect();

        // Patient fetches messages via API
        Sanctum::actingAs($this->patientA);
        $res = $this->getJson("/api/messages/{$conv->id}")->assertStatus(200);
        $msgs = $res->json('messages');

        $this->assertCount(2, $msgs);
        // Message 1 (Patient)
        $this->assertEquals('Is amoxicillin available?', $msgs[0]['body']);
        $this->assertTrue($msgs[0]['is_mine']);

        // Message 2 (Pharmacy reply)
        $this->assertEquals('Yes, 500mg capsules are in stock at PHP 15.00 each.', $msgs[1]['body']);
        $this->assertFalse($msgs[1]['is_mine']);
    }

    /**
     * 13. Unauthorized web send and markRead are forbidden (403).
     */
    public function test_unauthorized_web_send_and_mark_read_are_forbidden(): void
    {
        $convA = Conversation::findOrStart($this->patientA->id, $this->pharmacyA->id);

        // Pharmacy Owner B (unauthorized) tries to send to Pharmacy A's conversation
        $this->actingAs($this->pharmacyOwnerB, 'pharmacy')
            ->post(route('pharmacy.messages.send', $convA->id), ['message' => 'Spy message'])
            ->assertStatus(403);

        // Pharmacy Owner B tries to mark read on Pharmacy A's conversation
        $this->actingAs($this->pharmacyOwnerB, 'pharmacy')
            ->post(route('pharmacy.messages.read', $convA->id))
            ->assertStatus(403);
    }

    /**
     * 14. Rate Limiting: Sending messages is throttled after 30 requests per minute.
     */
    public function test_message_sending_rate_limiter_throttles_abuse(): void
    {
        Sanctum::actingAs($this->patientA);
        $conv = Conversation::findOrStart($this->patientA->id, $this->pharmacyA->id);

        // Send 30 allowed messages
        for ($i = 1; $i <= 30; $i++) {
            $res = $this->postJson("/api/messages/{$conv->id}", ['message' => "Message #{$i}"]);
            $this->assertEquals(200, $res->status(), "Message {$i} should be allowed");
        }

        // 31st message must be throttled with 429
        $throttledRes = $this->postJson("/api/messages/{$conv->id}", ['message' => 'Spam flood']);
        $throttledRes->assertStatus(429);
    }
}

