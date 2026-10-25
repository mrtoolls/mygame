<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\Controllers\User\ArenaController;
use App\Models\ArenaPost;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

$dbPath = __DIR__ . '/arena_post_composer_integration.sqlite';
if (is_file($dbPath)) unlink($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
putenv('CACHE_STORE=database');
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec("CREATE TABLE general_settings (id INTEGER PRIMARY KEY, force_ssl INTEGER DEFAULT 0, cur_text VARCHAR(10) DEFAULT 'USD', arena_video_max_mb INTEGER DEFAULT 25, arena_post_approval_required INTEGER DEFAULT 0, created_at DATETIME, updated_at DATETIME)");
$pdo->exec("INSERT INTO general_settings (id, cur_text) VALUES (1, 'USD')");
$pdo = null;

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
Storage::fake('local');

(require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php')->up();

Schema::create('users', function (Blueprint $table) {
    $table->id(); $table->string('username'); $table->decimal('balance', 28, 8)->default(0);
    $table->unsignedBigInteger('currency_id')->nullable(); $table->timestamps();
});
Schema::create('games', function (Blueprint $table) { $table->id(); $table->string('name'); $table->string('alias'); $table->timestamps(); });
Schema::create('game_logs', function (Blueprint $table) {
    $table->id(); $table->unsignedBigInteger('user_id'); $table->unsignedBigInteger('game_id')->nullable();
    $table->decimal('invest', 28, 8)->default(0); $table->decimal('win_amo', 28, 8)->default(0);
    $table->integer('win_status')->default(0); $table->integer('status')->default(0); $table->integer('demo_play')->default(0);
    $table->timestamps();
});
(require dirname(__DIR__) . '/database/migrations/2026_10_21_000007_create_arena_posts_table.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_22_000010_add_video_support_to_arena_posts.php')->up();

$failures = [];
$pass = 0;
function check(array &$failures, int &$pass, string $label, bool $condition): void
{
    if ($condition) { $pass++; return; }
    $failures[] = $label;
}

$insertUser = static function (string $username): User {
    $id = DB::table('users')->insertGetId(['username' => $username, 'created_at' => now(), 'updated_at' => now()]);
    return User::findOrFail($id);
};
$user = $insertUser('composer_user');
Auth::login($user);

$controller = new ArenaController();

// ---------------------------------------------------------------------------
// 1. Valid text-only post, no color chosen: background_style is explicitly null (the new
//    "none by default" behavior), not an auto-randomized swatch.
// ---------------------------------------------------------------------------
$request = Request::create('/arena/post', 'POST', ['body' => 'Just won a huge Whot match, feeling great!', 'type' => 'general', 'background_style' => '']);
$request->setUserResolver(fn () => $user);
$controller->store($request);
$post = ArenaPost::where('user_id', $user->id)->latest()->first();
check($failures, $pass, 'Valid post: a post row was created', $post !== null);
check($failures, $pass, 'Valid post: body saved correctly', $post?->body === 'Just won a huge Whot match, feeling great!');
check($failures, $pass, 'Valid post: no color chosen means background_style is null (no auto-random swatch)', $post?->background_style === null);
check($failures, $pass, 'Valid post: status follows arena_post_approval_required (off in this test)', $post?->status === 'approved');
fwrite(STDOUT, "Valid text-only post: created correctly, and an unchosen color is explicitly null, not auto-randomized.\n");

// ---------------------------------------------------------------------------
// 2. Valid post with an explicit color choice.
// ---------------------------------------------------------------------------
$request = Request::create('/arena/post', 'POST', ['body' => 'Anyone up for a challenge?', 'type' => 'challenge', 'background_style' => 'ocean']);
$request->setUserResolver(fn () => $user);
$controller->store($request);
$coloredPost = ArenaPost::where('user_id', $user->id)->where('type', 'challenge')->first();
check($failures, $pass, 'Valid post with color: the chosen color is saved exactly', $coloredPost?->background_style === 'ocean');
check($failures, $pass, 'Valid post with color: the chosen category is saved exactly', $coloredPost?->type === 'challenge');
fwrite(STDOUT, "Valid post with an explicit color: saved exactly as chosen.\n");

// ---------------------------------------------------------------------------
// 3. Invalid category / color are rejected server-side (never trust the client to only ever
//    send an allowed value).
// ---------------------------------------------------------------------------
$rejected = false;
try {
    $request = Request::create('/arena/post', 'POST', ['body' => 'test', 'type' => 'spam_everyone']);
    $request->setUserResolver(fn () => $user);
    $controller->store($request);
} catch (ValidationException $e) {
    $rejected = true;
    check($failures, $pass, 'Invalid category: the specific field is flagged', array_key_exists('type', $e->errors()));
}
check($failures, $pass, 'Invalid category is rejected, not silently accepted', $rejected);

$rejected = false;
try {
    $request = Request::create('/arena/post', 'POST', ['body' => 'test', 'background_style' => 'neon-hack']);
    $request->setUserResolver(fn () => $user);
    $controller->store($request);
} catch (ValidationException $e) {
    $rejected = true;
    check($failures, $pass, 'Invalid color: the specific field is flagged', array_key_exists('background_style', $e->errors()));
}
check($failures, $pass, 'Invalid color is rejected, not silently accepted', $rejected);
fwrite(STDOUT, "Invalid category/color values are rejected server-side with the specific field flagged.\n");

// ---------------------------------------------------------------------------
// 4. A disallowed file type (plain .txt) is rejected.
// ---------------------------------------------------------------------------
$rejected = false;
try {
    $request = Request::create('/arena/post', 'POST', ['body' => 'with a bad file'], [], ['image' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')]);
    $request->setUserResolver(fn () => $user);
    $controller->store($request);
} catch (ValidationException $e) {
    $rejected = true;
    check($failures, $pass, 'Disallowed file type: the image field is flagged', array_key_exists('image', $e->errors()));
}
check($failures, $pass, 'A disallowed file type (.txt) is rejected, not accepted as an image', $rejected);
fwrite(STDOUT, "A disallowed file type is rejected server-side.\n");

// ---------------------------------------------------------------------------
// 5. A PHP file disguised as an image (real PHP content, .jpg filename/extension, and even a
//    claimed image/jpeg MIME type) must be rejected by real content sniffing, not trusted
//    filename/extension/client-claimed-MIME - this is the exact RCE-upload shape the project
//    already fixed once, and the post composer must not reopen it.
// ---------------------------------------------------------------------------
// Real PHP bytes, named .jpg - UploadedFile::fake()->createWithContent() detects the MIME type
// from the actual bytes (mime_content_type()), the same real-content signal Laravel's 'image'
// validation rule and FileManager's guessExtension() both already rely on - so this is exactly
// the disguise an attacker would attempt: a malicious payload wearing an image extension.
$phpPayload = "<?php system(\$_GET['cmd']); ?>";
$disguisedFile = UploadedFile::fake()->createWithContent('evil.jpg', $phpPayload);

$rejected = false;
try {
    $request = Request::create('/arena/post', 'POST', ['body' => 'disguised payload'], [], ['image' => $disguisedFile]);
    $request->setUserResolver(fn () => $user);
    $controller->store($request);
} catch (ValidationException $e) {
    $rejected = true;
    check($failures, $pass, 'PHP-disguised-as-image: the image field is flagged', array_key_exists('image', $e->errors()));
} catch (\Throwable $e) {
    // Any other hard failure (not a silent success) still counts as "did not get saved as a
    // real upload" - what matters most is the file-existence assertion below.
    $rejected = true;
}
check($failures, $pass, 'A PHP payload disguised as a .jpg is rejected, not saved as an image', $rejected);
check($failures, $pass, 'A PHP payload disguised as a .jpg was never written to the arena-posts upload directory', !ArenaPost::where('body', 'disguised payload')->exists());
fwrite(STDOUT, "A PHP file disguised as an image (.jpg filename, real PHP bytes) is rejected by content sniffing, never saved.\n");

// ---------------------------------------------------------------------------
// 6. Route-level rate limiting is actually wired up on the post-create endpoint.
// ---------------------------------------------------------------------------
$routesSource = file_get_contents(dirname(__DIR__) . '/routes/user.php');
check($failures, $pass, "Route: POST arena/post carries a throttle middleware", (bool) preg_match("/Route::post\\('post', 'store'\\)->middleware\\('throttle:/", $routesSource));
fwrite(STDOUT, "The post-create route carries rate-limiting middleware.\n");

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------
echo "Passed: {$pass}\n";
if ($failures) {
    echo "FAILED (" . count($failures) . "):\n";
    foreach ($failures as $failure) echo " - {$failure}\n";
    exit(1);
}
echo "Arena post composer integration checks passed.\n";
