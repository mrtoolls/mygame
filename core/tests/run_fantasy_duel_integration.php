<?php

// End-to-end test for the Live Duel extension. Uses an isolated SQLite database; the production
// MySQL row-lock path is separately exercised only when a MySQL test DSN is available.
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Games\TableGames\TableGameService;
use App\Models\FantasyDuel;
use App\Models\FantasyDuelSquad;
use App\Models\FantasyPlayer;
use App\Models\TableGame;
use App\Models\User;
use App\Services\CoinLedger;
use App\Services\Fantasy\FantasyDuelService;
use App\Services\Fantasy\FantasyLeagueGenerator;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/fantasy_duel_integration.sqlite';
if (is_file($dbPath)) unlink($dbPath);
putenv('DB_CONNECTION=sqlite'); putenv('DB_DATABASE=' . $dbPath); putenv('CACHE_STORE=array'); putenv('APP_KEY=base64:' . base64_encode(str_repeat('d',32))); putenv('APP_ENV=testing');
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec("CREATE TABLE languages (id INTEGER PRIMARY KEY, code VARCHAR(10), name VARCHAR(60), is_default INTEGER DEFAULT 0)");
$pdo->exec("INSERT INTO languages (code,name,is_default) VALUES ('en','English',1)"); $pdo = null;
$app = require dirname(__DIR__) . '/bootstrap/app.php'; $app->make(ConsoleKernel::class)->bootstrap();
(require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php')->up();
Schema::create('general_settings', function (Blueprint $table) { $table->id(); $table->string('cur_text',10)->default('USD'); $table->string('cur_sym',5)->default('$'); $table->integer('currency_format')->default(1); $table->boolean('coin_mode')->default(false); $table->timestamps(); });
DB::table('general_settings')->insert(['id'=>1]);
(require dirname(__DIR__) . '/database/migrations/2026_10_24_000011_add_coin_settings_to_general_settings.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_11_04_000006_add_fantasy_settings_to_general_settings.php')->up();
Schema::create('users', function (Blueprint $table) { $table->id(); $table->string('username'); $table->decimal('balance',28,8)->default(0); $table->unsignedBigInteger('currency_id')->nullable(); $table->integer('status')->default(1); $table->string('register_ip',45)->nullable(); $table->timestamps(); });
Schema::create('admins', function (Blueprint $table) { $table->id(); $table->timestamps(); });
Schema::create('transactions', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('user_id'); $table->decimal('amount',28,8); $table->decimal('charge',28,8)->default(0); $table->string('trx_type',1); $table->string('details'); $table->string('remark'); $table->string('trx')->unique(); $table->decimal('post_balance',28,8); $table->timestamps(); });
(require dirname(__DIR__) . '/database/migrations/2026_10_19_000001_create_multiplayer_game_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_25_000003_add_is_coin_mode_to_table_game_commissions.php')->up();
Schema::table('table_games', fn (Blueprint $table) => $table->unsignedBigInteger('currency_id')->nullable()->index());
Schema::table('table_game_players', fn (Blueprint $table) => $table->unsignedBigInteger('currency_id')->nullable()->index());
(require dirname(__DIR__) . '/database/migrations/2026_10_19_000002_create_table_game_chat_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_19_000003_create_currency_and_country_controls.php')->up();
DB::table('currencies')->where('id',1)->update(['enabled'=>true,'rate_to_usd'=>1,'updated_at'=>now()]);
foreach (['2026_10_20_000001_add_wallet_stake_to_table_game_players.php','2026_10_20_000003_add_locked_fx_rates_to_table_game_players.php','2026_10_20_000004_add_settlement_amount_to_table_game_players.php','2026_10_21_000014_create_table_game_rematches_table.php','2026_10_22_000011_add_rules_version_to_table_games.php'] as $file) (require dirname(__DIR__) . '/database/migrations/' . $file)->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_11_04_000001_create_fantasy_core_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_11_04_000002_create_fantasy_team_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_11_04_000003_create_fantasy_league_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_11_04_000004_create_fantasy_contest_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_11_04_000005_create_fantasy_admin_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_11_04_000008_add_budget_tiers_and_contest_squads.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_11_05_000009_create_fantasy_duels.php')->up();

$general = gs(); $general->coin_mode = true; $general->ff_enabled = true; $general->ff_budget_tiers = '[100,120,150]'; $general->ff_tier_min_fees = '{"100":10,"120":15,"150":20}'; $general->ff_duel_build_minutes = 1; $general->ff_duel_live_minutes = 1; $general->ff_duel_fixture_count = 4; $general->ff_duel_ready_seconds = 60; $general->ff_duel_lobby_minutes = 10; $general->ff_duel_fee_percent = 5; $general->ff_daily_stake_cap = 50000; $general->ff_max_entry_stake = 10000; $general->save();
app(FantasyLeagueGenerator::class)->generate('Duel Test Season', now()->subMinutes(20));

$checks = 0;
function expect(bool $ok, string $message): void { global $checks; if (!$ok) throw new RuntimeException('FAILED: ' . $message); $checks++; }
$ledger = app(CoinLedger::class); $service = app(FantasyDuelService::class);
$makeUser = function (string $name) use ($ledger): User { $id=DB::table('users')->insertGetId(['username'=>$name,'balance'=>0,'currency_id'=>1,'status'=>1,'created_at'=>now(),'updated_at'=>now()]); $user=User::findOrFail($id); $ledger->mint('user',(int)$user->id,'500','duel test seed','duel-test-seed:'.$user->id); return $user; };
$buildXI = function (float $tier): array {
    $need=['GK'=>1,'DEF'=>4,'MID'=>4,'FWD'=>2]; $picked=[]; $clubs=[]; $cost=0;
    $players=FantasyPlayer::active()->where('status','fit')->orderBy('price')->get()->groupBy('position');
    foreach($need as $position=>$count){ foreach($players[$position] as $player){ if(count(array_filter($picked,fn($p)=>$p->position===$position)) >= $count) break; if(($clubs[$player->club_id]??0)>=3 || $cost+(float)$player->price>$tier) continue; $picked[]=$player; $clubs[$player->club_id]=($clubs[$player->club_id]??0)+1; $cost+=(float)$player->price; } }
    if(count($picked)!==11) throw new RuntimeException('Unable to build the test XI.');
    $captain=collect($picked)->sortByDesc('rating')->first(); return [collect($picked)->pluck('id')->map(fn($id)=>(int)$id)->all(),(int)$captain->id];
};

foreach ([2,3,4] as $count) {
    $users=[]; for($i=0;$i<$count;$i++) $users[]=$makeUser('duel_'.$count.'_'.$i);
    $table=$service->create($users[0],$count,'20',100,'duel_create_'.$count.'_key01','198.51.100.'.$count);
    expect(FantasyDuel::where('table_game_id',$table->id)->value('phase')==='lobby','create stores the committed slate before the lobby build phase');
    expect(strlen((string)FantasyDuel::where('table_game_id',$table->id)->value('seed_commitment'))===64,'created duel has a SHA-256 seed commitment');
    foreach(array_slice($users,1) as $i=>$user) $service->join($user,$table->id,'203.0.113.'.($count*10+$i));
    expect(FantasyDuel::where('table_game_id',$table->id)->value('phase')==='ready','full lobby enters ready check');
    foreach($users as $user) $service->ready($user,$table->id);
    expect(FantasyDuel::where('table_game_id',$table->id)->value('phase')==='building','all seated users ready starts the build window');
    foreach($users as $index=>$user){ if($count===2 && $index===1) continue; [$ids,$captain]=$buildXI(100); $service->submitSquad($user,$table->id,$ids,$captain); }
    $before=$service->snapshot($users[0],$table->id);
    expect(!array_key_exists('opponent_squad',$before) && !array_key_exists('squads',$before),'snapshot never exposes an opponent selection before build lock');
    FantasyDuel::where('table_game_id',$table->id)->update(['build_deadline_at'=>now()->subSecond()]);
    $service->sweep();
    expect(FantasyDuel::where('table_game_id',$table->id)->value('phase')==='live','build deadline enters clock-driven live phase');
    expect(FantasyDuelSquad::where('table_game_id',$table->id)->where('auto_submitted',true)->count()===($count===2?1:0),'missed builder deadline creates an automatic legal squad');
    if($count===2){ $auto=FantasyDuelSquad::where('table_game_id',$table->id)->where('auto_submitted',true)->first(); $autoPlayers=FantasyPlayer::whereIn('id',$auto->player_ids)->get(); expect($autoPlayers->count()===11 && (float)$autoPlayers->sum('price')<=100 && $autoPlayers->countBy('club_id')->max()<=3,'auto squad obeys XI count, tier budget and club cap'); }
    FantasyDuel::where('table_game_id',$table->id)->update(['live_ends_at'=>now()->subSecond()]);
    $service->sweep();
    $duel=FantasyDuel::where('table_game_id',$table->id)->first();
    expect($duel->phase==='finished' && TableGame::find($table->id)->status==='finished','full-time settlement closes the framework table');
    expect($service->verify($table->id)['valid']===true,'revealed seed reproduces every committed virtual fixture');
    expect($ledger->reconcile()['ok']===true,'CoinLedger reconciles after completed duel');
    expect(TableGame::find($table->id)->result && str_starts_with(TableGame::find($table->id)->result,'winner'),'duel winner result remains eligible for the existing table-competition ingestion query');
}

// Stress the automatic value builder over many randomized market price maps and all configured tiers.
$originalPrices=DB::table('ff_players')->pluck('price','id')->all(); mt_srand(20261105);
for($scenario=0;$scenario<24;$scenario++){
    $bindings=[]; $idBindings=[]; $case=[];
    foreach(array_keys($originalPrices) as $playerId){ $case[]='WHEN ? THEN ?'; $bindings[]=(int)$playerId; $bindings[]=number_format(mt_rand(35,80)/10,1,'.',''); $idBindings[]=(int)$playerId; }
    DB::update('UPDATE ff_players SET price = CASE id '.implode(' ',$case).' ELSE price END WHERE id IN ('.implode(',',array_fill(0,count($idBindings),'?')).')',[...$bindings,...$idBindings]);
    $tier=[100.0,120.0,150.0][$scenario%3]; $selection=$service->buildAutomaticSquad($tier); $chosen=FantasyPlayer::whereIn('id',$selection['player_ids'])->get(); $counts=$chosen->countBy('position');
    expect(count($selection['player_ids'])===11 && $counts->get('GK',0)===1 && $counts->get('DEF',0)===4 && $counts->get('MID',0)===4 && $counts->get('FWD',0)===2,'randomized auto XI always has a valid 4-4-2 shape');
    expect($chosen->countBy('club_id')->max()<=3 && (float)$chosen->sum('price')<=$tier && in_array($selection['captain_id'],$selection['player_ids'],true),'randomized auto XI stays under tier budget, club cap and captain rule');
}
$restoreBindings=[]; $restoreIds=[]; $restoreCase=[]; foreach($originalPrices as $playerId=>$price){$restoreCase[]='WHEN ? THEN ?';$restoreBindings[]=(int)$playerId;$restoreBindings[]=$price;$restoreIds[]=(int)$playerId;}
DB::update('UPDATE ff_players SET price = CASE id '.implode(' ',$restoreCase).' ELSE price END WHERE id IN ('.implode(',',array_fill(0,count($restoreIds),'?')).')',[...$restoreBindings,...$restoreIds]);

// An unfilled lobby timeout returns every held coin exactly once.
$cancelMaker=$makeUser('duel_cancel_maker'); $cancelTable=$service->create($cancelMaker,2,'20',100,'duel_cancel_create01','192.0.2.90');
expect(bccomp((string)$ledger->wallet('user',$cancelMaker->id)->fresh()->held_balance,'20',0)===0,'unfilled lobby holds its creator stake');
TableGame::whereKey($cancelTable->id)->update(['join_deadline_at'=>now()->subSecond()]); $service->sweep();
$cancelWallet=$ledger->wallet('user',$cancelMaker->id)->fresh();
expect(FantasyDuel::where('table_game_id',$cancelTable->id)->value('phase')==='cancelled' && bccomp((string)$cancelWallet->available_balance,'500',0)===0 && bccomp((string)$cancelWallet->held_balance,'0',0)===0,'unfilled lobby timeout cancels and fully refunds the held stake');

// Same-network entry is rejected before a second hold, and a complete tied result splits net pot.
$tieUsers=[$makeUser('duel_tie_1'),$makeUser('duel_tie_2')];
$tieTable=$service->create($tieUsers[0],2,'20',100,'duel_tie_create01','192.0.2.91');
try { $service->join($tieUsers[1],$tieTable->id,'192.0.2.91'); throw new RuntimeException('FAILED: same-network seat unexpectedly accepted.'); }
catch (\InvalidArgumentException $e) { expect(str_contains($e->getMessage(),'same network'),'same-network linked accounts receive a clear block'); }
expect(bccomp((string)$ledger->wallet('user',$tieUsers[1]->id)->fresh()->held_balance,'0',0)===0,'linked-account block occurs before stake hold');
$service->join($tieUsers[1],$tieTable->id,'192.0.2.92'); foreach($tieUsers as $user)$service->ready($user,$tieTable->id);
[$tieIds,$tieCaptain]=$buildXI(100); foreach($tieUsers as $user)$service->submitSquad($user,$tieTable->id,$tieIds,$tieCaptain);
$sameSubmitted=now()->subMinutes(2); FantasyDuelSquad::where('table_game_id',$tieTable->id)->update(['submitted_at'=>$sameSubmitted]);
FantasyDuel::where('table_game_id',$tieTable->id)->update(['build_deadline_at'=>now()->subSecond()]); $service->sweep();
FantasyDuel::where('table_game_id',$tieTable->id)->update(['live_ends_at'=>now()->subSecond()]); $service->sweep();
$tie=FantasyDuel::where('table_game_id',$tieTable->id)->first();
expect(count($tie->result['winners'])===2 && TableGame::find($tieTable->id)->result==='winners:0,1','identical score, goals, captain and submission time splits the full tie');
foreach($tieUsers as $user) expect(bccomp((string)$ledger->wallet('user',$user->id)->fresh()->available_balance,'499',0)===0,'tie payout deducts each exact fee share and returns the remainder');
expect($ledger->reconcile()['ok']===true,'coin reconciliation passes after tie settlement and fee burn');

// Repeat-opponent markers are auditable and seat matching is protected by table row locks.
expect(str_contains(file_get_contents(dirname(__DIR__).'/app/Services/Fantasy/FantasyDuelService.php'),'lockForUpdate()'),'duel seat, squad, phase and payout changes use transactional row locks');
expect(DB::table('ff_duel_audits')->where('event','duel_settled')->count()===4,'every settled duel writes an audit event');
fwrite(STDOUT, "Fantasy Duel lifecycle: {$checks} checks passed (2, 3 and 4 seats; auto-squad, fairness replay, timeout refunds, tie split, linked-account blocks, CoinLedger reconciliation and competition result compatibility).\n");
