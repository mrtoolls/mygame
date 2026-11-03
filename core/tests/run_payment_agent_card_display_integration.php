<?php

// The manual-payment page used to show a payment agent's bare email and a plain text blob of
// payment details. It now shows a verified badge on the avatar's corner, structured payment
// method cards, and a "Contact agent" popover built from whichever channels that specific agent
// actually has enabled (in-app chat, WhatsApp, Telegram, phone, email) - falling back to the
// plain email line only when nothing at all is configured (covers agents who haven't set up
// contact methods yet, and deposits placed before this shipped). This renders the real page's
// own markup (not a reimplementation) against several snapshot shapes to prove each case.

require dirname(__DIR__) . '/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

$dbPath = __DIR__ . '/payment_agent_card_display_integration.sqlite';
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
Schema::create('general_settings', function (Blueprint $table) {
    $table->id();
    $table->string('site_name', 190)->default('Test Site');
    $table->string('cur_text', 10)->default('USD');
    $table->string('cur_sym', 5)->default('$');
    $table->integer('currency_format')->default(1);
    $table->timestamps();
});
DB::table('general_settings')->insert(['id' => 1]);

$passed = 0;
function check(bool $cond, string $message): void
{
    global $passed;
    if (!$cond) throw new RuntimeException("FAILED: $message");
    $passed++;
}

// manual.blade.php @extends the full site master layout and ends with <x-viser-form>, a
// DB-backed component unrelated to the payment-agent card this test targets - render only the
// @section body up to (not including) that component, which is everything this change touched.
$viewPath = resource_path('views/templates/nocturne/user/payment/manual.blade.php');
$raw = file_get_contents($viewPath);
$raw = preg_replace('/@extends\([^)]*\)/', '', $raw, 1);
$raw = preg_replace('/@section\([^)]*\)/', '', $raw, 1);
$cut = strpos($raw, '<x-viser-form');
if ($cut === false) throw new RuntimeException('Could not find the <x-viser-form> cut point - has manual.blade.php changed shape?');
$raw = substr($raw, 0, $cut) . "\n</div></div></form></div></div></div>\n@push('script')@endpush";
$tmpPath = sys_get_temp_dir() . '/isolated_manual_payment_' . md5($viewPath) . '.blade.php';
file_put_contents($tmpPath, $raw);
View::addLocation(sys_get_temp_dir());

$render = static function (array $snapshot) use ($tmpPath) {
    // The real $data is a Deposit Eloquent model, accessed in the blade both as an array
    // ($data['amount']) and as an object ($data->gateway, $data->payment_agent_snapshot) -
    // Fluent supports both the same way a model does.
    $data = new \Illuminate\Support\Fluent([
        'amount' => '50.00000000',
        'final_amount' => '54.50000000',
        'method_currency' => 'USD',
        'gateway' => (object) ['description' => '', 'form_id' => 1],
        'payment_agent_snapshot' => json_decode(json_encode($snapshot)), // matches the real `object` cast's shape exactly.
    ]);
    return view('isolated_manual_payment_' . md5(resource_path('views/templates/nocturne/user/payment/manual.blade.php')), ['data' => $data, 'gateway' => $data->gateway])->render();
};

// --- A fully-configured agent: verified, chat enabled, two contact channels, a bank method. ---
$html = $render([
    'id' => 7, 'name' => 'Creed Brown', 'email' => 'creed@test.com', 'photo' => null,
    'is_verified' => true, 'is_chat_agent' => true,
    'contact_methods' => [
        'whatsapp' => ['enabled' => true, 'value' => '+2348012345678'],
        'telegram' => ['enabled' => false, 'value' => '@creed'],
        'phone' => ['enabled' => false, 'value' => ''],
        'email' => ['enabled' => true, 'value' => 'pay@creed.test'],
    ],
    'payment_details' => [
        'methods' => [['type' => 'bank', 'label' => 'GTBank NGN', 'bank_name' => 'GTBank', 'account_number' => '0123456789', 'account_name' => 'Creed Brown']],
        'instructions' => 'Include your username as the reference.',
    ],
]);
check(str_contains($html, 'pa-avatar__badge'), 'A verified agent shows the badge on the avatar corner.');
check(str_contains($html, 'pa-contact'), 'A contact popover is shown when channels are available.');
check(str_contains($html, 'data-open-support-agent="7"'), 'The in-app chat option targets this specific agent.');
check(str_contains($html, 'wa.me/2348012345678'), 'WhatsApp (enabled) is offered with digits-only in the link.');
check(!str_contains($html, 't.me/creed'), 'Telegram (disabled) is NOT offered even though a value is stored.');
check(str_contains($html, 'mailto:pay@creed.test'), 'Email (enabled) is offered.');
check(str_contains($html, '0123456789') && str_contains($html, 'pa-copy'), 'The bank method shows the account number with a copy button.');
check(str_contains($html, 'Include your username as the reference.'), 'Separate instructions are shown alongside the structured method.');
fwrite(STDOUT, "A fully-configured verified agent shows the avatar badge, a contact popover with only the enabled channels, a structured bank method, and separate instructions.\n");

// --- A bare-minimum legacy agent: not verified, chat off, no contact methods, legacy string
// payment_details (a deposit placed before this feature shipped, or an agent who never
// configured anything new) - must gracefully fall back, not show an empty/broken popover. ---
$html = $render([
    'id' => 9, 'name' => 'Legacy Agent', 'email' => 'legacy@test.com', 'photo' => null,
    'is_verified' => false, 'is_chat_agent' => false, 'contact_methods' => null,
    'payment_details' => 'Pay to GTBank 0123456789, Legacy Agent.',
]);
check(!str_contains($html, 'pa-avatar__badge'), 'An unverified agent shows no badge.');
check(!str_contains($html, 'pa-contact'), 'No contact popover is shown when nothing is configured.');
check(str_contains($html, 'legacy@test.com'), 'Falls back to the plain email line when there is truly nothing else to offer.');
check(str_contains($html, 'Pay to GTBank 0123456789, Legacy Agent.'), "The agent's legacy plain-text payment details still render.");
fwrite(STDOUT, "A legacy/unconfigured agent degrades gracefully: no badge, no empty popover, plain email and legacy text details still shown.\n");

// --- Chat-only: no structured contact channels configured, but chat is enabled - the popover
// must still appear with just that one option, not be hidden entirely. ---
$html = $render([
    'id' => 11, 'name' => 'Chat Only', 'email' => 'chatonly@test.com', 'photo' => null,
    'is_verified' => false, 'is_chat_agent' => true, 'contact_methods' => null,
    'payment_details' => ['methods' => [], 'instructions' => 'Call the office line listed on our site.'],
]);
check(str_contains($html, 'pa-contact'), 'The contact popover appears for chat-only availability.');
check(str_contains($html, 'data-open-support-agent="11"'), 'Chat-in-app is offered.');
check(!str_contains($html, 'chatonly@test.com'), 'The plain email fallback is NOT shown once a real contact option exists.');
fwrite(STDOUT, "An agent with only in-app chat enabled still gets a working contact popover instead of falling back to a bare email line.\n");

fwrite(STDOUT, "Passed: $passed\n");
fwrite(STDOUT, "Payment agent card display integration checks passed.\n");
