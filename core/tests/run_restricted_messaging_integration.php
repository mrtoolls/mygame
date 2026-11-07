<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\Conversation;
use App\Models\CoinVendor;
use App\Models\Message;
use App\Models\SupportAgent;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\MessagingService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$dbPath = __DIR__ . '/restricted_messaging_integration.sqlite';
if (is_file($dbPath)) unlink($dbPath);
touch($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
putenv('CACHE_STORE=database');
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec("CREATE TABLE general_settings (id INTEGER PRIMARY KEY, cur_text VARCHAR(10) DEFAULT 'USD', site_name VARCHAR(191) DEFAULT 'Winxpot', push_template TEXT, created_at DATETIME, updated_at DATETIME)");
$pdo->exec("INSERT INTO general_settings (id) VALUES (1)");
$pdo = null;

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

(require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php')->up();

Schema::create('users', function (Blueprint $table) {
    $table->id(); $table->string('username'); $table->string('fullname')->nullable(); $table->unsignedBigInteger('currency_id')->nullable(); $table->timestamps();
});
Schema::create('currencies', function (Blueprint $table) {
    $table->id(); $table->char('code', 3)->unique(); $table->string('name'); $table->string('symbol', 12);
    $table->unsignedTinyInteger('decimals')->default(2); $table->boolean('enabled')->default(true);
    $table->decimal('rate_to_usd', 24, 12)->nullable(); $table->timestamps();
});
DB::table('currencies')->insert(['id' => 1, 'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'enabled' => 1, 'rate_to_usd' => 1, 'created_at' => now(), 'updated_at' => now()]);
Schema::create('support_agents', function (Blueprint $table) {
    $table->id(); $table->string('name'); $table->string('username'); $table->boolean('is_active')->default(true);
    $table->boolean('is_chat_agent')->default(true); $table->unsignedBigInteger('user_id')->nullable(); $table->timestamps();
});

(require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_25_000001_add_pause_state_to_coin_rates.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_24_000020_create_coin_marketplace_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_26_000001_create_coin_vendor_system.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_11_07_000003_add_contact_methods_to_coin_vendors.php')->up();

Schema::create('notification_templates', function (Blueprint $table) {
    $table->id(); $table->string('name'); $table->string('act'); $table->string('subject')->nullable();
    $table->boolean('email_status')->default(0); $table->text('email_body')->nullable();
    $table->boolean('sms_status')->default(0); $table->text('sms_body')->nullable();
    $table->boolean('push_status')->default(0); $table->string('push_title')->nullable(); $table->text('push_body')->nullable();
    $table->text('shortcodes')->nullable(); $table->timestamps();
});
(require dirname(__DIR__) . '/database/migrations/2026_11_07_000001_create_in_app_notification_channel.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_11_07_000004_create_restricted_messaging_tables.php')->up();

$insertUser = static function (string $username): User {
    $id = DB::table('users')->insertGetId(['username' => $username, 'fullname' => $username, 'currency_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
    return User::findOrFail($id);
};

$messaging = new MessagingService();
$failures = [];
$pass = 0;
function check(array &$failures, int &$pass, string $label, bool $condition): void
{
    if ($condition) { $pass++; return; }
    $failures[] = $label;
}

// 1. Two plain members can never start a conversation with each other.
$memberA = $insertUser('plain_member_a');
$memberB = $insertUser('plain_member_b');
$blocked = false;
try { $messaging->startConversation('user', $memberA->id, 'user', $memberB->id, 'Hey there'); }
catch (InvalidArgumentException $e) { $blocked = true; }
check($failures, $pass, 'Two plain members cannot start a conversation', $blocked);
check($failures, $pass, 'No conversation row was created for the blocked attempt', Conversation::count() === 0);
fwrite(STDOUT, "Plain user-to-user messaging: correctly blocked.\n");

// 2. A user CAN start a conversation with an active CoinVendor.
$vendorUser = $insertUser('msg_vendor');
$buyer = $insertUser('msg_buyer');
CoinVendor::create(['user_id' => $vendorUser->id, 'application_id' => 1, 'status' => 'active', 'is_selling' => true, 'currency_id' => 1, 'sell_rate' => '0.01', 'per_trade_limit' => 100, 'daily_limit' => 500]);
$conversation = $messaging->startConversation('user', $buyer->id, 'user', $vendorUser->id, 'What is your rate for 500 coins?');
check($failures, $pass, 'User can message an active CoinVendor', (bool) $conversation->id);
check($failures, $pass, 'The first message was recorded', Message::where('conversation_id', $conversation->id)->count() === 1);
check($failures, $pass, 'Conversation last_message_preview was set', str_contains($conversation->last_message_preview, 'rate for 500 coins'));
$vendorNotif = UserNotification::where('user_id', $vendorUser->id)->where('type', 'NEW_CHAT_MESSAGE')->first();
check($failures, $pass, 'The vendor received a real NEW_CHAT_MESSAGE notification', (bool) $vendorNotif);
check($failures, $pass, 'The buyer (sender) did NOT get notified about their own message', UserNotification::where('user_id', $buyer->id)->where('type', 'NEW_CHAT_MESSAGE')->count() === 0);
fwrite(STDOUT, "User -> CoinVendor messaging: works, and fires a real notification to the vendor.\n");

// 3. Starting again between the same pair reuses the SAME conversation (canonical dedup), not a new one.
$countBefore = Conversation::count();
$sameConversation = $messaging->startConversation('user', $buyer->id, 'user', $vendorUser->id, 'Following up...');
check($failures, $pass, 'Re-starting between the same pair reuses the same conversation (no duplicate)', Conversation::count() === $countBefore && $sameConversation->id === $conversation->id);
check($failures, $pass, 'Both messages landed in the one conversation', Message::where('conversation_id', $conversation->id)->count() === 2);
fwrite(STDOUT, "Conversation dedup: the same pair always resolves to one conversation, regardless of who re-initiates.\n");

// 4. Reversing who initiates (vendor -> buyer) ALSO resolves to the same conversation.
$fromVendorSide = Conversation::between('user', $vendorUser->id, 'user', $buyer->id);
check($failures, $pass, 'Vendor-initiated lookup resolves to the same canonical conversation', $fromVendorSide->id === $conversation->id);
fwrite(STDOUT, "Canonical ordering: (buyer,vendor) and (vendor,buyer) resolve to the identical row.\n");

// 5. An active SupportAgent CAN message any user (agent-initiated), and the user can reply back.
$agentId = DB::table('support_agents')->insertGetId(['name' => 'Jordan Agent', 'username' => 'jordanagent', 'is_active' => 1, 'is_chat_agent' => 1, 'created_at' => now(), 'updated_at' => now()]);
$plainUser = $insertUser('agent_target_user');
$agentConversation = $messaging->startConversation('agent', $agentId, 'user', $plainUser->id, 'Hi, following up on your KYC.');
check($failures, $pass, 'Agent can message any plain user', (bool) $agentConversation->id);
$userNotif = UserNotification::where('user_id', $plainUser->id)->where('type', 'NEW_CHAT_MESSAGE')->first();
check($failures, $pass, 'The user received a real notification from the agent', (bool) $userNotif);
check($failures, $pass, 'Notification message mentions the agent name', $userNotif && str_contains($userNotif->message, 'Jordan Agent'));

$reply = $messaging->sendMessage($agentConversation, 'user', $plainUser->id, 'Thanks, all done!');
check($failures, $pass, 'The user can reply back to the agent', (bool) $reply->id);
check($failures, $pass, 'Agent-side unread count reflects the user\'s reply', $messaging->unreadCountFor('agent', $agentId) === 1);
check($failures, $pass, 'User-side unread count still shows the agent\'s un-viewed first message', $messaging->unreadCountFor('user', $plainUser->id) === 1);
fwrite(STDOUT, "Agent -> user messaging: agent-initiated conversations work, and the user can reply.\n");

// 6. A plain user CANNOT start a conversation with another plain user even by going through
//    startConversation() with swapped argument order (symmetry check).
$blockedReversed = false;
try { $messaging->startConversation('user', $memberB->id, 'user', $memberA->id, 'Hi'); }
catch (InvalidArgumentException $e) { $blockedReversed = true; }
check($failures, $pass, 'Permission check is symmetric regardless of argument order', $blockedReversed);

// 7. markRead() only clears the READER's own unread count, leaving the other side untouched.
$messaging->markRead($agentConversation, 'agent', $agentId);
check($failures, $pass, 'markRead() clears the agent\'s unread count', $messaging->unreadCountFor('agent', $agentId) === 0);
check($failures, $pass, 'markRead() for the agent does not touch the user\'s own unread count', $messaging->unreadCountFor('user', $plainUser->id) === 1);
$messaging->markRead($agentConversation, 'user', $plainUser->id);
check($failures, $pass, 'markRead() for the user then clears their own unread count too', $messaging->unreadCountFor('user', $plainUser->id) === 0);
fwrite(STDOUT, "markRead(): correctly scoped per-reader - clearing one side never clears the other.\n");

// 8. Sending an empty message is rejected.
$emptyRejected = false;
try { $messaging->sendMessage($conversation, 'user', $buyer->id, '   '); }
catch (InvalidArgumentException $e) { $emptyRejected = true; }
check($failures, $pass, 'Empty/whitespace-only messages are rejected', $emptyRejected);

// 9. A non-participant cannot send into someone else's conversation.
$nonParticipant = $insertUser('nosy_outsider');
$intrusionBlocked = false;
try { $messaging->sendMessage($conversation, 'user', $nonParticipant->id, 'let me in'); }
catch (InvalidArgumentException $e) { $intrusionBlocked = true; }
check($failures, $pass, 'A non-participant cannot send a message into someone else\'s conversation', $intrusionBlocked);
fwrite(STDOUT, "Guardrails: empty messages and non-participant sends are both rejected.\n");

echo "Passed: {$pass}\n";
if ($failures) {
    echo "FAILED (" . count($failures) . "):\n";
    foreach ($failures as $failure) echo " - {$failure}\n";
    exit(1);
}
echo "Restricted messaging checks passed.\n";
