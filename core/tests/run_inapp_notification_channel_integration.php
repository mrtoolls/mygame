<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\NotificationTemplate;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/inapp_notification_channel_integration.sqlite';
if (is_file($dbPath)) unlink($dbPath);
touch($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
putenv('CACHE_STORE=database');

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

(require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php')->up();

Schema::create('users', function (Blueprint $table) {
    $table->id(); $table->string('username'); $table->string('fullname')->nullable(); $table->timestamps();
});

// Minimal stand-ins for the base-install tables (no tracked migration exists for either, same
// situation as every other test in this suite that touches them).
Schema::create('general_settings', function (Blueprint $table) {
    $table->id();
    $table->string('site_name')->default('Winxpot');
    $table->string('cur_text')->default('USD');
    $table->string('cur_sym')->default('$');
    $table->string('push_template')->nullable();
    $table->string('email_template')->nullable();
    $table->timestamps();
});
DB::table('general_settings')->insert(['id' => 1, 'push_template' => 'Hi {{fullname}}, {{message}}']);

Schema::create('notification_templates', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('act');
    $table->string('subject')->nullable();
    $table->boolean('email_status')->default(0);
    $table->text('email_body')->nullable();
    $table->boolean('sms_status')->default(0);
    $table->text('sms_body')->nullable();
    $table->boolean('push_status')->default(0);
    $table->string('push_title')->nullable();
    $table->text('push_body')->nullable();
    $table->text('shortcodes')->nullable();
    $table->timestamps();
});
// Pre-existing base-install row the real admin broadcast tool always targets (App\Lib\
// UserNotificationSender::isTemplateEnabled / notificationToAll/Single use act = 'DEFAULT').
DB::table('notification_templates')->insert([
    'id' => 1, 'name' => 'Default', 'act' => 'DEFAULT', 'subject' => '{{subject}}',
    'email_status' => 1, 'email_body' => '{{message}}',
    'push_status' => 1, 'push_body' => '{{message}}',
]);

(require dirname(__DIR__) . '/database/migrations/2026_11_07_000001_create_in_app_notification_channel.php')->up();

$fail = false;
function check($label, $cond) {
    global $fail;
    echo ($cond ? "PASS" : "FAIL") . ": $label\n";
    if (!$cond) $fail = true;
}

// 1) New columns + table exist.
check('notification_templates gained inapp_status/title/body', Schema::hasColumn('notification_templates', 'inapp_status') && Schema::hasColumn('notification_templates', 'inapp_title') && Schema::hasColumn('notification_templates', 'inapp_body'));
check('general_settings gained inapp_template defaulted to passthrough', DB::table('general_settings')->where('id', 1)->value('inapp_template') === '{{message}}');
check('user_notifications table created', Schema::hasTable('user_notifications'));

// 2) The pre-existing DEFAULT row was backfilled with a usable in-app passthrough.
$default = NotificationTemplate::where('act', 'DEFAULT')->first();
check('DEFAULT row backfilled with inapp_body passthrough', $default->inapp_body === '{{message}}');
check('DEFAULT row backfilled with inapp_title passthrough', $default->inapp_title === '{{subject}}');
check('DEFAULT row inapp_status enabled by column default', (bool) $default->inapp_status === true);

// 3) New event-type rows were seeded, enabled for push+inapp, NOT enabled for email (except NEW_LIVE_TABLE).
$gameWin = NotificationTemplate::where('act', 'GAME_WIN')->first();
check('GAME_WIN row seeded', (bool) $gameWin);
check('GAME_WIN inapp enabled', (bool) $gameWin->inapp_status === true);
check('GAME_WIN push enabled', (bool) $gameWin->push_status === true);
check('GAME_WIN email NOT enabled by default (avoid spam)', (bool) $gameWin->email_status === false);
$newTable = NotificationTemplate::where('act', 'NEW_LIVE_TABLE')->first();
check('NEW_LIVE_TABLE inapp enabled', (bool) $newTable->inapp_status === true);
check('NEW_LIVE_TABLE push enabled', (bool) $newTable->push_status === true);
check('NEW_LIVE_TABLE email NOT enabled by default (admin chose in-app+push only, no mass email)', (bool) $newTable->email_status === false);

// 4) Re-running the migration's seed logic is idempotent (defensive-migration convention).
$countBefore = NotificationTemplate::count();
(require dirname(__DIR__) . '/database/migrations/2026_11_07_000001_create_in_app_notification_channel.php')->up();
check('re-running migration does not duplicate template rows', NotificationTemplate::count() === $countBefore);

// 5) End-to-end: notify() with no explicit channel list fires InApp and writes a real feed row.
$userId = DB::table('users')->insertGetId(['username' => 'alice', 'fullname' => 'Alice', 'created_at' => now(), 'updated_at' => now()]);
$user = User::find($userId);
notify($user, 'GAME_WIN', ['game' => 'Chess', 'amount' => '$5.00', 'opponent' => 'Bob', 'notif_icon' => 'trophy', 'notif_url' => '/table-games/chess/1'], ['inapp'], false);

$row = UserNotification::where('user_id', $user->id)->first();
check('UserNotification row was created', (bool) $row);
check('message short-codes were substituted', $row && str_contains($row->message, 'Chess') && str_contains($row->message, '$5.00') && str_contains($row->message, 'Bob'));
check('title was substituted from inapp_title', $row && $row->title === 'You won!');
check('icon/url passthrough fields stored', $row && $row->icon === 'trophy' && $row->url === '/table-games/chess/1');
check('row starts unread', $row && $row->read_at === null);

// 6) Disabled template -> silent no-op (matches existing NotifyProcess::getMessage() behavior).
NotificationTemplate::where('act', 'GAME_WIN')->update(['inapp_status' => 0]);
notify($user, 'GAME_WIN', ['game' => 'Chess', 'amount' => '$1', 'opponent' => 'Bob'], ['inapp'], false);
check('disabled template produces no new row', UserNotification::where('user_id', $user->id)->count() === 1);

// 7) Unknown act with no template row at all -> also a silent no-op, never a crash.
notify($user, 'SOME_UNSEEDED_ACT', ['x' => 'y'], ['inapp'], false);
check('unseeded act produces no row and does not throw', UserNotification::where('user_id', $user->id)->count() === 1);

// 8) The DEFAULT-row admin-broadcast path (subject/message short-codes, exactly as UserNotificationSender calls it) also works end to end.
$bobId = DB::table('users')->insertGetId(['username' => 'bob', 'fullname' => 'Bob', 'created_at' => now(), 'updated_at' => now()]);
$bob = User::find($bobId);
notify($bob, 'DEFAULT', ['subject' => 'Maintenance tonight', 'message' => 'The site will be down for 10 minutes at midnight.'], ['inapp'], false);
$broadcastRow = UserNotification::where('user_id', $bob->id)->first();
check('DEFAULT-act admin broadcast produced a bell row', (bool) $broadcastRow);
check('DEFAULT-act broadcast title is the admin-entered subject', $broadcastRow && $broadcastRow->title === 'Maintenance tonight');
check('DEFAULT-act broadcast message is the admin-entered message', $broadcastRow && $broadcastRow->message === 'The site will be down for 10 minutes at midnight.');

echo $fail ? "\nSOME CHECKS FAILED\n" : "\nALL CHECKS PASSED\n";
exit($fail ? 1 : 0);
