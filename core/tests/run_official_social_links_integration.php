<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\Controllers\Admin\SocialLinkController;
use App\Models\SocialLink;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/official_social_links_integration.sqlite';
if (is_file($dbPath)) unlink($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
putenv('CACHE_STORE=array');
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec("CREATE TABLE languages (id INTEGER PRIMARY KEY, code VARCHAR(10), name VARCHAR(60), is_default INTEGER DEFAULT 0)");
$pdo->exec("INSERT INTO languages (code, name, is_default) VALUES ('en', 'English', 1)");
$pdo = null;

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

Schema::create('social_links', function (Blueprint $table) {
    $table->id();
    $table->string('platform', 30)->unique();
    $table->string('label', 60);
    $table->string('url')->nullable();
    $table->boolean('is_active')->default(false);
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->timestamps();
});
DB::table('social_links')->insert([
    'platform' => 'x', 'label' => 'X (Twitter)', 'url' => 'https://x.com/winxpot', 'is_active' => true,
    'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
]);

$passed = 0;
function check(bool $condition, string $message): void
{
    global $passed;
    if (!$condition) throw new RuntimeException("FAILED: $message");
    $passed++;
}

$migration = require dirname(__DIR__) . '/database/migrations/2026_11_05_000010_add_whatsapp_to_official_social_links.php';
$migration->up();
$migration->up();
$whatsapp = SocialLink::where('platform', 'whatsapp')->firstOrFail();
check(SocialLink::where('platform', 'whatsapp')->count() === 1, 'The migration safely adds exactly one WhatsApp row when upgrading an existing installation.');
check($whatsapp->label === 'WhatsApp' && $whatsapp->is_active && $whatsapp->url === null, 'WhatsApp appears as an enabled, unconfigured admin option.');
check($whatsapp->iconClass() === 'ph-fill ph-whatsapp-logo', 'WhatsApp resolves to its Phosphor brand icon.');

$request = Request::create('/admin/social-links', 'POST', [
    'url' => [$whatsapp->id => 'https://chat.whatsapp.com/WinxpotCommunity'],
    'active' => [$whatsapp->id => '1'],
]);
$request->headers->set('referer', '/admin/social-links');
(new SocialLinkController())->update($request);
$whatsapp->refresh();
check($whatsapp->url === 'https://chat.whatsapp.com/WinxpotCommunity' && $whatsapp->is_active, 'Admin can save and activate the WhatsApp group URL using the existing social-links form.');
check(SocialLink::active()->whereKey($whatsapp->id)->exists(), 'Configured WhatsApp appears in the active official-links collection rendered by the modal.');

$adminView = file_get_contents(dirname(__DIR__) . '/resources/views/admin/social_links/index.blade.php');
$officialView = file_get_contents(dirname(__DIR__) . '/resources/views/templates/nocturne/partials/official_button.blade.php');
check(str_contains($adminView, '@foreach ($links as $link)') && str_contains($officialView, '@foreach($officialLinks as $link)'), 'Admin and official modal both render database-driven social links, including the new WhatsApp row.');

fwrite(STDOUT, "Official social links WhatsApp migration, admin configuration, active modal link, and icon checks passed.\nPassed: {$passed}\n");
