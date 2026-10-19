<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\MoneyConversionAudit;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/money_audit_test.sqlite';
if (is_file($dbPath)) unlink($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec('CREATE TABLE general_settings (id INTEGER PRIMARY KEY, force_ssl INTEGER DEFAULT 0)');
$pdo = null;
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
Schema::create('currencies', function (Blueprint $table) { $table->id(); $table->char('code', 3); $table->unsignedTinyInteger('decimals'); });
Schema::create('users', function (Blueprint $table) { $table->id(); $table->decimal('balance', 28, 8); $table->unsignedBigInteger('currency_id')->nullable(); });
Schema::create('transactions', function (Blueprint $table) { $table->id(); $table->decimal('amount', 28, 8); $table->unsignedBigInteger('currency_id')->nullable(); });
$currencyId = DB::table('currencies')->insertGetId(['code' => 'USD', 'decimals' => 2]);
DB::table('users')->insert([['balance' => '10.00', 'currency_id' => $currencyId], ['balance' => '1.001', 'currency_id' => $currencyId]]);
DB::table('transactions')->insert([['amount' => '2.50', 'currency_id' => $currencyId], ['amount' => '0.001', 'currency_id' => null]]);
$report = app(MoneyConversionAudit::class)->run();
$find = static fn ($table, $column) => collect($report['fields'])->firstWhere(fn ($row) => $row['table'] === $table && $row['column'] === $column);
$userBalance = collect($report['fields'])->first(fn ($row) => $row['table'] === 'users' && $row['column'] === 'balance');
$txAmount = collect($report['fields'])->first(fn ($row) => $row['table'] === 'transactions' && $row['column'] === 'amount');
if ($userBalance['fractional_precision_issues'] !== 1 || $txAmount['fractional_precision_issues'] !== 1 || $txAmount['currency_unassigned'] !== 1) throw new RuntimeException('Money conversion audit detected unexpected precision or assignment counts.');
$balanceExamples = collect($userBalance['precision_issue_examples']);
if ($balanceExamples->count() !== 1 || $balanceExamples->first()['id'] != 2 || $balanceExamples->first()['amount_value'] != '1.001' || $balanceExamples->first()['currency_code'] !== 'USD') throw new RuntimeException('Money conversion audit did not identify the precision issue record details.');
DB::disconnect();
unlink($dbPath);
echo "Money conversion audit precision and missing-currency checks passed.\n";
