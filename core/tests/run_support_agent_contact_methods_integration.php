<?php

// A payment agent can now offer WhatsApp/Telegram/phone/email as contact channels on their
// public payment card, each independently toggled with its own value, separate from the
// always-available in-app chat option. This proves: enabling a channel without a value is
// rejected, a valid submission saves the {enabled, value} shape per channel, disabling a channel
// (while leaving its text field untouched in the client - which submits no value at all since a
// disabled HTML input never posts) preserves its last-known value instead of erasing it, and a
// non-payment agent is refused from this page entirely (it would never be shown anywhere for
// them).

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\Controllers\SupportAgentController;
use App\Models\SupportAgent;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

$dbPath = __DIR__ . '/support_agent_contact_methods_integration.sqlite';
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

Schema::create('support_agents', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('username')->unique();
    $table->string('password');
    $table->boolean('is_active')->default(true);
    $table->boolean('is_payment_agent')->default(false);
    $table->text('payment_details')->nullable();
    $table->timestamps();
});

(require dirname(__DIR__) . '/database/migrations/2026_11_03_000002_add_contact_methods_to_support_agents.php')->up();

$passed = 0;
function check(bool $cond, string $message): void
{
    global $passed;
    if (!$cond) throw new RuntimeException("FAILED: $message");
    $passed++;
}

check(Schema::hasColumn('support_agents', 'contact_methods'), 'contact_methods column was added.');

$agentId = DB::table('support_agents')->insertGetId([
    'name' => 'Rae', 'email' => 'rae@test.com', 'username' => 'rae_agent', 'password' => Hash::make('whatever1'),
    'is_payment_agent' => true, 'created_at' => now(), 'updated_at' => now(),
]);
\Illuminate\Support\Facades\Auth::guard('support_agent')->loginUsingId($agentId);
$controller = new SupportAgentController();

// --- Enabling a channel without a value is rejected. ---
try {
    $controller->contactSettingsUpdate(Request::create('/x', 'POST', ['whatsapp_enabled' => '1']));
    throw new RuntimeException('Enabling WhatsApp with no number was accepted.');
} catch (ValidationException $e) {
    check(array_key_exists('whatsapp_value', $e->errors()), 'Enabling a channel with no value is rejected.');
}
fwrite(STDOUT, "Enabling a contact channel with no value is rejected.\n");

// --- A valid submission saves the structured per-channel shape. ---
$controller->contactSettingsUpdate(Request::create('/x', 'POST', [
    'whatsapp_enabled' => '1', 'whatsapp_value' => '+2348012345678',
    'email_enabled' => '1', 'email_value' => 'rae@example.com',
]));
$agent = SupportAgent::find($agentId);
check($agent->contact_methods['whatsapp']['enabled'] === true, 'WhatsApp saved as enabled.');
check($agent->contact_methods['whatsapp']['value'] === '+2348012345678', 'WhatsApp number saved correctly.');
check($agent->contact_methods['email']['enabled'] === true, 'Email saved as enabled.');
check($agent->contact_methods['telegram']['enabled'] === false, 'Telegram (never touched) defaults to disabled.');
fwrite(STDOUT, "A valid submission saves the structured {enabled, value} shape per channel.\n");

// --- Disabling WhatsApp (its field submits nothing, matching a disabled HTML input) preserves
// the stored number instead of erasing it. ---
$controller->contactSettingsUpdate(Request::create('/x', 'POST', [
    'email_enabled' => '1', 'email_value' => 'rae@example.com',
    // whatsapp_enabled and whatsapp_value both omitted - exactly what a disabled <input> submits.
]));
$agent = $agent->fresh();
check($agent->contact_methods['whatsapp']['enabled'] === false, 'WhatsApp is now disabled.');
check($agent->contact_methods['whatsapp']['value'] === '+2348012345678', 'WhatsApp number is preserved while disabled, not erased - re-enabling later restores it.');
fwrite(STDOUT, "Disabling a channel preserves its last value instead of silently wiping it.\n");

\Illuminate\Support\Facades\Auth::guard('support_agent')->logout();

// --- A non-payment agent cannot reach this page at all. ---
$nonPaymentAgentId = DB::table('support_agents')->insertGetId([
    'name' => 'Sam', 'email' => 'sam@test.com', 'username' => 'sam_agent', 'password' => Hash::make('whatever1'),
    'is_payment_agent' => false, 'created_at' => now(), 'updated_at' => now(),
]);
\Illuminate\Support\Facades\Auth::guard('support_agent')->loginUsingId($nonPaymentAgentId);
try {
    $controller->contactSettings();
    throw new RuntimeException('A non-payment agent was allowed to view contact settings.');
} catch (HttpException $e) {
    check($e->getStatusCode() === 403, 'A non-payment agent is refused (403) from contact settings - it would never be shown anywhere for them.');
}

fwrite(STDOUT, "Passed: $passed\n");
fwrite(STDOUT, "Support agent contact methods integration checks passed.\n");
