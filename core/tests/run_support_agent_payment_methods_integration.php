<?php

// Admin's "Edit Support Agent" page used to collect a payment agent's receiving details as one
// free-text blob. The admin asked for a structured builder instead: pick a method type (bank
// transfer / mobile money / crypto / other), fill in the fields that type actually needs (a bank
// transfer needs an account number, a crypto method needs a wallet address), with instructions
// kept as a separate field. This proves: per-type required-field validation rejects an
// incomplete method, a valid submission saves the structured {"methods":[...],"instructions":...}
// shape, fields that don't belong to a method's type are dropped even if present in the submitted
// data (switching a row's type client-side doesn't clear old fields), turning off "payment agent"
// clears payment_details entirely, and the one-time migration that normalizes any pre-existing
// plain-text payment_details into the new shape is correct and idempotent.

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\Controllers\Admin\SupportAgentController;
use App\Models\SupportAgent;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

$dbPath = __DIR__ . '/support_agent_payment_methods_integration.sqlite';
if (is_file($dbPath)) unlink($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
putenv('CACHE_STORE=database');
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
    $table->boolean('is_chat_agent')->default(true);
    $table->boolean('is_news_editor')->default(false);
    $table->unsignedBigInteger('user_id')->nullable();
    $table->unsignedBigInteger('country_id')->nullable();
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->timestamp('last_seen_at')->nullable();
    $table->timestamps();
});

$passed = 0;
function check(bool $cond, string $message): void
{
    global $passed;
    if (!$cond) throw new RuntimeException("FAILED: $message");
    $passed++;
}

$paymentRowTemplate = file_get_contents(dirname(__DIR__) . '/resources/views/admin/support/agents/partials/payment_method_row.blade.php');
$adminFormTemplate = file_get_contents(dirname(__DIR__) . '/resources/views/admin/support/agents/form.blade.php');
check(str_contains($paymentRowTemplate, "@disabled(\$type !== 'bank')") && str_contains($paymentRowTemplate, "@disabled(\$type !== 'mobile_money')"), 'Only the selected payment type has enabled inputs, so its account_name cannot be overwritten by a hidden duplicate field.');
check(str_contains($adminFormTemplate, 'field.disabled = !active;'), 'Changing payment method type enables only that type\'s fields before submit.');
fwrite(STDOUT, "Inactive payment method fields are disabled, preventing duplicate account_name inputs from overwriting the visible value.\n");

$controller = new SupportAgentController();

// --- An incomplete bank method (missing account_number/account_name) is rejected. ---
$request = Request::create('/x', 'POST', [
    'name' => 'Ada', 'email' => 'ada@test.com', 'username' => 'ada_agent', 'password' => 'supersecret1',
    'is_payment_agent' => '1',
    'payment_methods' => [0 => ['type' => 'bank', 'bank_name' => 'First Bank']],
]);
try {
    $controller->store($request);
    throw new RuntimeException('An incomplete bank method was accepted.');
} catch (ValidationException $e) {
    check(array_key_exists('payment_methods.0.account_number', $e->errors()), 'Missing account_number is rejected for a bank method.');
    check(array_key_exists('payment_methods.0.account_name', $e->errors()), 'Missing account_name is rejected for a bank method.');
}
fwrite(STDOUT, "An incomplete bank transfer method (missing account number/name) is rejected with field-specific errors.\n");

// --- A complete bank + crypto submission saves the structured shape. ---
$request = Request::create('/x', 'POST', [
    'name' => 'Ada', 'email' => 'ada@test.com', 'username' => 'ada_agent', 'password' => 'supersecret1',
    'is_payment_agent' => '1',
    'payment_methods' => [
        0 => ['type' => 'bank', 'label' => 'GTBank NGN', 'bank_name' => 'GTBank', 'account_number' => '0123456789', 'account_name' => 'Ada Lovelace'],
        1 => ['type' => 'crypto', 'network' => 'TRC20', 'wallet_address' => 'TXaBc123'],
    ],
    'payment_instructions' => 'Include your username as the transfer reference.',
]);
$controller->store($request);
$agent = SupportAgent::where('username', 'ada_agent')->firstOrFail();
check($agent->is_payment_agent === true, 'The agent is saved as a payment agent.');
check(is_array($agent->payment_details), 'payment_details decodes as an array (the array cast works on the existing TEXT column).');
check(count($agent->payment_details['methods']) === 2, 'Both methods were saved.');
check($agent->payment_details['methods'][0]['type'] === 'bank' && $agent->payment_details['methods'][0]['account_number'] === '0123456789', 'The bank method kept its structured fields.');
check($agent->payment_details['methods'][1]['type'] === 'crypto' && $agent->payment_details['methods'][1]['wallet_address'] === 'TXaBc123', 'The crypto method kept its wallet address.');
check($agent->payment_details['instructions'] === 'Include your username as the transfer reference.', 'The separate instructions field was saved apart from the structured methods.');
fwrite(STDOUT, "A complete submission (bank + crypto methods, plus separate instructions) saves the structured {methods, instructions} shape.\n");

// --- Fields that don't belong to a method's type are dropped even if present in the request
// (simulates switching a row's type client-side without clearing the old type's fields). ---
$request = Request::create('/x', 'POST', [
    'name' => 'Ada', 'email' => 'ada@test.com', 'username' => 'ada_agent', 'password' => '',
    'is_payment_agent' => '1',
    'payment_methods' => [
        0 => ['type' => 'crypto', 'network' => 'ERC20', 'wallet_address' => 'TXnewWallet', 'bank_name' => 'Stale Bank', 'account_number' => 'StaleAcct'],
    ],
    'payment_instructions' => 'Updated instructions.',
]);
$controller->update($request, $agent->id);
$agent = $agent->fresh();
check(count($agent->payment_details['methods']) === 1, 'The update replaced the method list (one method now, not merged with the old two).');
check(!isset($agent->payment_details['methods'][0]['bank_name']), 'A stale bank_name submitted alongside a crypto type is dropped, not saved.');
check($agent->payment_details['methods'][0]['wallet_address'] === 'TXnewWallet', 'The crypto wallet address was saved correctly.');
fwrite(STDOUT, "Fields left over from switching a method's type client-side are dropped server-side, never saved cross-type.\n");

// --- Turning off "payment agent" clears payment_details entirely. ---
$request = Request::create('/x', 'POST', [
    'name' => 'Ada', 'email' => 'ada@test.com', 'username' => 'ada_agent', 'password' => '',
]);
$controller->update($request, $agent->id);
$agent = $agent->fresh();
check($agent->is_payment_agent === false, 'The agent is no longer a payment agent.');
check($agent->payment_details === null, 'payment_details is cleared entirely once the agent is no longer a payment agent.');
fwrite(STDOUT, "Unchecking \"payment agent\" clears payment_details entirely, not just hides it.\n");

// --- A payment agent with no methods and no instructions is rejected. ---
$request = Request::create('/x', 'POST', [
    'name' => 'Ada', 'email' => 'ada@test.com', 'username' => 'ada_agent', 'password' => '',
    'is_payment_agent' => '1',
]);
try {
    $controller->update($request, $agent->id);
    throw new RuntimeException('A payment agent with no methods and no instructions was accepted.');
} catch (ValidationException $e) {
    check(array_key_exists('payment_methods', $e->errors()), 'A payment agent needs at least one method or instructions.');
}
fwrite(STDOUT, "A payment agent with neither a method nor instructions is rejected.\n");

// --- The one-time migration normalizes pre-existing plain-text payment_details, and is
// idempotent on a second run. ---
$legacyId = DB::table('support_agents')->insertGetId([
    'name' => 'Legacy Bob', 'email' => 'bob@test.com', 'username' => 'legacy_bob', 'password' => 'x',
    'is_payment_agent' => 1, 'payment_details' => 'Pay to GTBank 0123456789, Bob Smith. Include your username.',
    'created_at' => now(), 'updated_at' => now(),
]);
$migration = require dirname(__DIR__) . '/database/migrations/2026_10_28_000001_structure_agent_payment_details.php';
$migration->up();
$legacy = SupportAgent::find($legacyId);
check(is_array($legacy->payment_details), 'The legacy plain-text value now decodes as an array.');
check($legacy->payment_details['methods'] === [], 'The legacy value has no structured methods (it was never one).');
check($legacy->payment_details['instructions'] === 'Pay to GTBank 0123456789, Bob Smith. Include your username.', "The agent's original text is preserved as instructions, not dropped.");

$migration->up(); // Re-run: must not double-wrap.
$legacy = $legacy->fresh();
check($legacy->payment_details['instructions'] === 'Pay to GTBank 0123456789, Bob Smith. Include your username.', 'Re-running the migration is idempotent - the value is not wrapped a second time.');
fwrite(STDOUT, "The normalization migration preserves existing agents' plain-text payment details as instructions, and is safe to re-run.\n");

fwrite(STDOUT, "Passed: $passed\n");
fwrite(STDOUT, "Support agent structured payment methods integration checks passed.\n");
