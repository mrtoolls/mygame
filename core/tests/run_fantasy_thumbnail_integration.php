<?php

// Fantasy Football has no row in the games table to hang an admin-uploaded image column off of
// (every other game's card image is a column on its own Game model row) - this proves
// fantasyThumbnailUrl() correctly falls back to the bundled placeholder asset before an admin has
// uploaded anything, and correctly resolves through the SAME getImage()/getFilePath('game') path
// every other game's card image uses once FantasyAdminController::thumbnailUpdate() has stored one.

require dirname(__DIR__) . '/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/fantasy_thumbnail_integration.sqlite';
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

(require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php')->up();
Schema::create('general_settings', function (Blueprint $table) {
    $table->id();
    $table->string('cur_text', 10)->default('USD');
    $table->timestamps();
});
DB::table('general_settings')->insert(['id' => 1]);
(require dirname(__DIR__) . '/database/migrations/2026_11_04_000007_add_card_image_to_general_settings_for_fantasy.php')->up();

$passed = 0;
function check(bool $cond, string $message): void {
    global $passed;
    if (!$cond) throw new RuntimeException("FAILED: $message");
    $passed++;
}

// --- Before any admin upload: falls back to the bundled placeholder asset, not a broken image. ---
check(gs('ff_card_image') === null, 'No thumbnail has been uploaded yet.');
$fallbackUrl = fantasyThumbnailUrl();
check(str_contains($fallbackUrl, 'assets/images/frontend/fantasy-football/thumbnail.jpg'), "Falls back to the bundled placeholder asset before any admin upload - got '$fallbackUrl'.");
fwrite(STDOUT, "Before any admin upload, the card shows the bundled placeholder instead of a broken image link.\n");

// --- After an admin upload: resolves through the exact same getImage()/getFilePath('game') path every other game's card image uses. ---
$general = gs();
$general->ff_card_image = 'fantasy-thumb-abc123.jpg';
$general->save();
\Illuminate\Support\Facades\Cache::forget('GeneralSetting');

$uploadedUrl = fantasyThumbnailUrl();
$expectedUrl = getImage(getFilePath('game') . '/fantasy-thumb-abc123.jpg', getFileSize('game'));
check($uploadedUrl === $expectedUrl, "Once uploaded, resolves through the identical getImage()/getFilePath('game') helper chain every other game's card image uses - got '$uploadedUrl', expected '$expectedUrl'.");
check(!str_contains($uploadedUrl, 'fantasy-football/thumbnail.jpg'), 'No longer falls back to the placeholder once a real upload exists.');
fwrite(STDOUT, "After an admin uploads a thumbnail (via the same fileUploader()/FileManager path as every other game), the card resolves it exactly like any other game's image.\n");

fwrite(STDOUT, "Passed: $passed\n");
fwrite(STDOUT, "Fantasy Football thumbnail integration checks passed.\n");
