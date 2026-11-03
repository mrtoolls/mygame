<?php

// Today almost nothing an agent can do is actually enforced server-side - any active agent can
// hit live chat, payments, and news regardless of what the admin intended; only Arena moderation
// was ever checked. This proves: the new migration backfills is_chat_agent/is_news_editor as
// TRUE for existing agents (nobody silently loses access they already had) while being idempotent
// on a second run; the agent-facing controller now actually 403s chat/payments/news when the
// corresponding capability is off, not just hides the nav link; the admin form can link a support
// agent to a player account by username; and a linked player can bridge into their agent session
// via the new one-click dashboard route without affecting their own player session, while an
// unlinked player gets a 404, not an open door.

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\Controllers\Admin\SupportAgentController as AdminSupportAgentController;
use App\Http\Controllers\SupportAgentController;
use App\Http\Controllers\User\UserController;
use App\Models\SupportAgent;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

$dbPath = __DIR__ . '/support_agent_capabilities_integration.sqlite';
if (is_file($dbPath)) unlink($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
putenv('CACHE_STORE=database');
putenv('APP_KEY=base64:' . base64_encode(random_bytes(32)));
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec("CREATE TABLE languages (id INTEGER PRIMARY KEY, code VARCHAR(10), name VARCHAR(60), is_default INTEGER DEFAULT 0)");
$pdo->exec("INSERT INTO languages (code, name, is_default) VALUES ('en', 'English', 1)");
$pdo = null;

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

(require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php')->up();

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('username')->unique();
    $table->string('email')->nullable();
    $table->string('password')->nullable();
    $table->timestamps();
});

Schema::create('support_agents', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('username')->unique();
    $table->string('password');
    $table->string('photo')->nullable();
    $table->string('title')->nullable();
    $table->text('bio')->nullable();
    $table->boolean('is_active')->default(true);
    $table->boolean('is_payment_agent')->default(false);
    $table->text('payment_details')->nullable();
    $table->json('accepted_payment_types')->nullable();
    $table->boolean('is_verified')->default(false);
    $table->unsignedTinyInteger('speed_override')->nullable();
    $table->boolean('is_moderator')->default(false);
    $table->boolean('is_marketplace_supervisor')->default(false);
    $table->boolean('is_selling_coins')->default(false);
    $table->unsignedBigInteger('country_id')->nullable();
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->timestamp('last_seen_at')->nullable();
    $table->timestamps();
});

Schema::create('support_tickets', function (Blueprint $table) {
    $table->id();
    $table->string('ticket')->nullable();
    $table->unsignedBigInteger('user_id')->default(0);
    $table->unsignedBigInteger('admin_id')->default(0);
    $table->unsignedBigInteger('support_agent_id')->nullable();
    $table->string('name')->nullable();
    $table->string('email')->nullable();
    $table->string('subject')->nullable();
    $table->string('channel', 20)->default('ticket');
    $table->tinyInteger('status')->default(0);
    $table->tinyInteger('priority')->default(0);
    $table->uuid('chat_uuid')->nullable()->unique();
    $table->char('visitor_token', 64)->nullable();
    $table->timestamp('last_reply')->nullable();
    $table->timestamps();
});

Schema::create('news_posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('slug')->unique();
    $table->string('badge_label')->nullable();
    $table->string('excerpt')->nullable();
    $table->text('body');
    $table->string('image')->nullable();
    $table->tinyInteger('status')->default(1);
    $table->string('posted_by_type')->nullable();
    $table->unsignedBigInteger('posted_by_id')->nullable();
    $table->string('posted_by_name')->nullable();
    $table->timestamps();
});

// A pre-existing agent row, inserted BEFORE the migration runs - simulates an already-live agent
// who today can freely use chat/news (nothing gates them), to prove the migration's defaults
// don't silently take that access away.
$preMigrationAgentId = DB::table('support_agents')->insertGetId([
    'name' => 'Pre-existing Agent', 'email' => 'pre@test.com', 'username' => 'pre_agent', 'password' => Hash::make('whatever1'),
    'created_at' => now(), 'updated_at' => now(),
]);

$migration = require dirname(__DIR__) . '/database/migrations/2026_11_03_000001_add_capability_flags_to_support_agents.php';
$migration->up();

$passed = 0;
function check(bool $cond, string $message): void
{
    global $passed;
    if (!$cond) throw new RuntimeException("FAILED: $message");
    $passed++;
}

check(Schema::hasColumn('support_agents', 'is_chat_agent'), 'is_chat_agent column was added.');
check(Schema::hasColumn('support_agents', 'is_news_editor'), 'is_news_editor column was added.');
check(Schema::hasColumn('support_agents', 'user_id'), 'user_id column was added.');

$preAgent = SupportAgent::find($preMigrationAgentId);
check($preAgent->is_chat_agent === true, 'A pre-existing agent keeps live chat access after the migration (default true).');
check($preAgent->is_news_editor === true, 'A pre-existing agent keeps news access after the migration (default true).');
check($preAgent->user_id === null, 'A pre-existing agent has no linked player account by default.');

$migration->up(); // Re-run: must not error or change anything.
check(SupportAgent::find($preMigrationAgentId)->is_chat_agent === true, 'Re-running the migration is idempotent.');
fwrite(STDOUT, "Migration: is_chat_agent/is_news_editor default true for existing agents (no silent access loss); user_id is a nullable new link; re-running is safe.\n");

// --- Admin can link a support agent to a player account by username, and toggle the new
// capability flags; new-agent defaults match the admin form (chat on, others off). ---
$playerId = DB::table('users')->insertGetId(['username' => 'linked_player', 'created_at' => now(), 'updated_at' => now()]);

$adminController = new AdminSupportAgentController();
$storeRequest = Request::create('/x', 'POST', [
    'name' => 'Nova', 'email' => 'nova@test.com', 'username' => 'nova_agent', 'password' => 'supersecret1',
    'is_chat_agent' => '1', 'linked_username' => 'linked_player',
]);
$adminController->store($storeRequest);
$novaAgent = SupportAgent::where('username', 'nova_agent')->firstOrFail();
check($novaAgent->is_chat_agent === true, 'A new agent with the chat box checked is saved as a chat agent.');
check($novaAgent->is_news_editor === false, 'A new agent with the news box unchecked is NOT a news editor (explicit opt-in).');
check($novaAgent->is_marketplace_supervisor === false, 'A new agent is not a marketplace supervisor unless granted.');
check((int) $novaAgent->user_id === $playerId, 'The agent is linked to the player account by username.');
fwrite(STDOUT, "Admin create: capability flags save correctly, and an agent can be linked to a player account by username.\n");

$updateRequest = Request::create('/x', 'POST', [
    'name' => 'Nova', 'email' => 'nova@test.com', 'username' => 'nova_agent', 'password' => '',
    'is_news_editor' => '1', 'is_marketplace_supervisor' => '1',
    // is_chat_agent omitted -> unchecked -> should turn OFF.
]);
$adminController->update($updateRequest, $novaAgent->id);
$novaAgent = $novaAgent->fresh();
check($novaAgent->is_chat_agent === false, 'Unchecking live chat on edit actually turns it off.');
check($novaAgent->is_news_editor === true, 'Checking news editor on edit turns it on.');
check($novaAgent->is_marketplace_supervisor === true, 'Checking marketplace supervisor on edit turns it on.');
check($novaAgent->user_id === null, 'Leaving the linked-username field blank on edit clears the link.');
fwrite(STDOUT, "Admin update: toggling a capability off actually turns it off (not just hides the nav link), and clearing the username field unlinks the player.\n");

// --- Server-side enforcement: a chat-disabled agent is refused by the agent-facing controller,
// not just missing a nav link. ---
$agentController = new SupportAgentController();
Auth::guard('support_agent')->loginUsingId($novaAgent->id); // is_chat_agent is false right now.
try {
    $agentController->conversations();
    throw new RuntimeException('A chat-disabled agent was allowed to list conversations.');
} catch (HttpException $e) {
    check($e->getStatusCode() === 403, 'A chat-disabled agent is refused (403) from the live chat endpoint.');
}

$novaAgent->is_chat_agent = true;
$novaAgent->is_news_editor = false;
$novaAgent->save();
Auth::guard('support_agent')->setUser($novaAgent->fresh()); // The guard caches the resolved user - sync it after mutating directly via Eloquent.
try {
    $agentController->newsIndex();
    throw new RuntimeException('A news-disabled agent was allowed to list news posts.');
} catch (HttpException $e) {
    check($e->getStatusCode() === 403, 'A news-disabled agent is refused (403) from the news endpoints.');
}

$novaAgent->is_payment_agent = false;
$novaAgent->save();
Auth::guard('support_agent')->setUser($novaAgent->fresh());
try {
    $agentController->paymentQueue();
    throw new RuntimeException('A non-payment agent was allowed to view the payment queue.');
} catch (HttpException $e) {
    check($e->getStatusCode() === 403, 'A non-payment agent is refused (403) from the payment queue - this had ZERO server-side gate before this change.');
}
fwrite(STDOUT, "Enforcement: chat/news/payments all now genuinely refuse an agent lacking that capability, closing a real gap where any active agent could use them regardless of intent.\n");
Auth::guard('support_agent')->logout();

// --- The user-side bridge: a linked player reaches their agent session with one click, without
// losing their own player session; an unlinked player gets a 404, not an open door. ---
$novaAgent->is_chat_agent = true;
$novaAgent->is_active = true;
$novaAgent->user_id = $playerId;
$novaAgent->save();

Auth::guard('web')->loginUsingId($playerId);
$userController = new UserController();
$response = $userController->agentDashboard();
check($response->getTargetUrl() === route('support-agent.dashboard'), 'The bridge redirects straight to the agent dashboard.');
check(Auth::guard('support_agent')->check() && (int) Auth::guard('support_agent')->id() === $novaAgent->id, 'The player is now authenticated into their linked agent session.');
check(Auth::guard('web')->check() && (int) Auth::guard('web')->id() === $playerId, 'The player is STILL authenticated as themselves - the bridge does not log them out of their own account.');
fwrite(STDOUT, "Bridge: a linked player reaches their agent dashboard with one click and keeps their own player session intact.\n");

Auth::guard('support_agent')->logout();
$strangerId = DB::table('users')->insertGetId(['username' => 'no_agent_here', 'created_at' => now(), 'updated_at' => now()]);
Auth::guard('web')->loginUsingId($strangerId);
try {
    $userController->agentDashboard();
    throw new RuntimeException('A player with no linked agent profile was let into an agent session.');
} catch (HttpException $e) {
    check($e->getStatusCode() === 404, 'A player with no linked agent profile gets a 404, not access to someone else\'s agent session.');
}
fwrite(STDOUT, "Bridge: a player with no linked agent gets a clean 404.\n");

fwrite(STDOUT, "Passed: $passed\n");
fwrite(STDOUT, "Support agent capabilities and user-link integration checks passed.\n");
