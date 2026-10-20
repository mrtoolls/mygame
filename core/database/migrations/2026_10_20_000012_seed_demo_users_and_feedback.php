<?php

use App\Constants\Status;
use App\Models\Country;
use App\Models\Currency;
use App\Models\User;
use App\Models\UserFeedback;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Demo/launch-preview data only. Every seeded user's email ends in @demo.winxpot.test and
     * every username starts with "demo_" so they're trivially identifiable and can be bulk
     * deleted later with:
     *   DELETE FROM users WHERE email LIKE '%@demo.winxpot.test';
     *   (user_feedback.user_id is a nullable FK with cascade-to-null, so feedback rows survive
     *   as anonymous entries if deleted first is skipped - delete user_feedback first, or rely
     *   on the FK's null-on-delete and clean those up too via a WHERE display_name LIKE 'Demo%'.)
     * Demo login password for all of them (not that anyone needs it): Demo@12345
     */
    public function up(): void
    {
        if (!Schema::hasTable('users') || User::where('email', 'like', '%@demo.winxpot.test')->exists()) return;

        $maleFirst = ['James', 'Michael', 'David', 'John', 'Robert', 'William', 'Daniel', 'Joseph', 'Thomas', 'Christopher', 'Kwame', 'Chidi', 'Emeka', 'Tunde', 'Femi', 'Kofi', 'Kojo', 'Sipho', 'Thabo', 'Brian', 'Kevin', 'Peter', 'Mark', 'Steven', 'Ibrahim'];
        $femaleFirst = ['Mary', 'Jennifer', 'Linda', 'Elizabeth', 'Susan', 'Jessica', 'Sarah', 'Karen', 'Nancy', 'Lisa', 'Ngozi', 'Amara', 'Chiamaka', 'Funmi', 'Adaeze', 'Ama', 'Abena', 'Thandiwe', 'Nomvula', 'Grace', 'Faith', 'Joy', 'Mercy', 'Patience', 'Blessing'];
        $lastNames = ['Johnson', 'Williams', 'Brown', 'Jones', 'Smith', 'Davis', 'Miller', 'Wilson', 'Moore', 'Taylor', 'Okafor', 'Eze', 'Adeyemi', 'Okoro', 'Nwosu', 'Mensah', 'Asante', 'Dlamini', 'Khumalo', 'Mwangi', 'Otieno', 'Kamau', 'Banda', 'Phiri', 'Mutale'];

        // (ISO country code, currency code) pairs limited to currencies the earlier currency/
        // country migration actually seeded, so every demo user gets a real, resolvable wallet.
        $locales = [['US', 'USD'], ['GB', 'GBP'], ['NG', 'NGN'], ['GH', 'GHS'], ['KE', 'KES'], ['UG', 'UGX'], ['TZ', 'TZS'], ['ZA', 'ZAR'], ['CM', 'XAF'], ['ZM', 'ZMW'], ['SL', 'SLE'], ['RW', 'RWF']];
        $countryJson = (array) json_decode(file_get_contents(resource_path('views/partials/country.json')), true);

        $hashedPassword = Hash::make('Demo@12345');
        $users = [];

        for ($i = 1; $i <= 50; $i++) {
            $isMale = $i % 2 === 1;
            $firstname = $isMale ? $maleFirst[($i - 1) % count($maleFirst)] : $femaleFirst[($i - 1) % count($femaleFirst)];
            $lastname = $lastNames[($i * 7) % count($lastNames)];
            [$iso, $currencyCode] = $locales[$i % count($locales)];
            $countryInfo = $countryJson[$iso] ?? ['country' => 'United States', 'dial_code' => '1'];
            $currency = Currency::where('code', $currencyCode)->first();
            $country = Country::where('iso_code', $iso)->first();
            $avatarPool = glob('assets/images/profilepics/' . ($isMale ? 'male' : 'female') . '*.png');

            $user = new User();
            $user->firstname = $firstname;
            $user->lastname = $lastname;
            $user->username = 'demo_' . strtolower($firstname) . $i;
            $user->email = 'demo_' . strtolower($firstname) . $i . '@demo.winxpot.test';
            $user->password = $hashedPassword;
            $user->gender = $isMale ? 'male' : 'female';
            $user->image = !empty($avatarPool) ? 'assets/images/profilepics/' . basename($avatarPool[array_rand($avatarPool)]) : null;
            $user->country_code = $iso;
            $user->country_name = $countryInfo['country'];
            $user->dial_code = $countryInfo['dial_code'];
            $user->mobile = (string) random_int(700000000, 799999999);
            $user->country_id = $country?->id;
            $user->currency_id = $currency?->id ?? $country?->currency_id;
            $user->balance = number_format(mt_rand(1000, 80000) / 100, 8, '.', '');
            $user->status = Status::USER_ACTIVE;
            $user->ev = Status::VERIFIED;
            $user->sv = Status::VERIFIED;
            $user->kv = Status::KYC_VERIFIED;
            $user->profile_complete = Status::YES;
            $user->created_at = now()->subDays(random_int(1, 120))->subMinutes(random_int(0, 1440));
            $user->updated_at = $user->created_at;
            $user->save();

            $users[] = $user;
        }

        $openers = [
            'Been playing here for a few weeks now and honestly the live tables feel so smooth.',
            'Withdrawals came through way faster than I expected, no complaints at all.',
            'Love the variety of games - chess and ludo with real money stakes is such a fun idea.',
            'Customer support actually replied within minutes when I had a deposit question.',
            'The app feels polished, works great on my phone and barely any lag.',
            'Won a decent amount on blackjack last night, cashout was painless.',
            'Really enjoy the live chat while playing, makes it feel like a real table with friends.',
            'Clean design, easy to navigate, and the VIP rewards are a nice touch.',
            'First time trying an online table games site and this exceeded my expectations.',
            'The draughts board looks amazing now, genuinely feels like a real board.',
            'Been recommending this to my friends, the whole experience just feels trustworthy.',
            'Gifting feature during live games is such a creative addition, love it.',
            'Smooth signup process and my deposit was credited instantly.',
            'The spades and hearts tables are exactly what I was looking for online.',
            'Great platform overall, just wish there were a few more table stakes options.',
            'Fast, reliable, and the mobile experience feels like a native app.',
            'Really impressed with how fair and transparent the games feel.',
            'The ludo board redesign is incredible, way better than other apps I have tried.',
            'Support team resolved my withdrawal question same day, very professional.',
            'Solid platform, good variety, and the interface keeps getting better.',
        ];

        foreach ($openers as $index => $message) {
            $user = $users[($index * 3 + 2) % count($users)];
            $rating = $index % 5 === 0 ? 4 : 5;
            UserFeedback::create([
                'user_id' => $user->id,
                'display_name' => substr($user->username, 0, 3) . '***',
                'player_since' => (int) $user->created_at->format('Y'),
                'rating' => $rating,
                'message' => $message,
                'public_consent' => true,
                'status' => 'approved',
                'approved_at' => now()->subDays(random_int(0, 60)),
            ]);
        }
    }

    public function down(): void
    {
        UserFeedback::whereHas('user', fn ($q) => $q->where('email', 'like', '%@demo.winxpot.test'))->delete();
        User::where('email', 'like', '%@demo.winxpot.test')->delete();
    }
};
