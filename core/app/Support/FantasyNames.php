<?php

namespace App\Support;

/**
 * Purely fictional name pools for the virtual league generator - no real club, player, league,
 * or sponsor name anywhere. Club names are built by combining an invented place-word with a
 * generic football suffix (e.g. "Ironbridge Rovers"); player names combine invented first/last
 * name pools. Combinatorially large enough that a 20-club, ~500-player league never collides
 * within one generation run (LeagueGenerator still de-duplicates defensively).
 */
final class FantasyNames
{
    private const CLUB_PLACE_WORDS = [
        'Ironbridge', 'Northgate', 'Redmoor', 'Silverlake', 'Ashford', 'Brackwell', 'Crownhill',
        'Dunmore', 'Eastvale', 'Falconwood', 'Greymouth', 'Harrow Point', 'Ivybrook', 'Kingsmere',
        'Longstone', 'Millhaven', 'Norwood Park', 'Oakfield', 'Pinehurst', 'Queensport',
        'Ridgeway', 'Stonebridge', 'Thornfield', 'Upton Vale', 'Westbury', 'Yewbrook', 'Castlemoor',
        'Fenbridge', 'Hartwell', 'Marsh End',
    ];

    private const CLUB_SUFFIXES = ['United', 'City', 'Rovers', 'Athletic', 'Town', 'Albion', 'Wanderers', 'FC'];

    private const CLUB_COLORS = [
        ['#1d4ed8', '#ffffff'], ['#dc2626', '#1f1f1f'], ['#16a34a', '#ffffff'], ['#f59e0b', '#1f1f1f'],
        ['#7c3aed', '#ffffff'], ['#0891b2', '#1f1f1f'], ['#111827', '#f59e0b'], ['#be123c', '#ffffff'],
        ['#047857', '#ffffff'], ['#1e293b', '#38bdf8'], ['#ea580c', '#1f1f1f'], ['#4338ca', '#ffffff'],
        ['#65a30d', '#1f1f1f'], ['#9d174d', '#ffffff'], ['#0f766e', '#ffffff'], ['#b91c1c', '#facc15'],
        ['#1d4ed8', '#facc15'], ['#374151', '#ffffff'], ['#15803d', '#facc15'], ['#581c87', '#ffffff'],
    ];

    private const PATTERNS = ['solid', 'stripes', 'hoops', 'halves'];

    private const FIRST_NAMES = [
        'Aiden', 'Marcus', 'Theo', 'Kai', 'Noah', 'Leon', 'Diego', 'Mateo', 'Sami', 'Jonas',
        'Felix', 'Omar', 'Luca', 'Rafael', 'Ezra', 'Milo', 'Zane', 'Dante', 'Hugo', 'Elias',
        'Ivo', 'Kofi', 'Niko', 'Pablo', 'Tomas', 'Viktor', 'Youssef', 'Adrian', 'Bruno', 'Caleb',
        'Darius', 'Emil', 'Gideon', 'Hassan', 'Idris', 'Jamal', 'Kenji', 'Lars', 'Mykel', 'Nasir',
        'Oscar', 'Pavel', 'Quinn', 'Reza', 'Stefan', 'Tariq', 'Ulric', 'Vance', 'Wyatt', 'Xavier',
    ];

    private const LAST_NAMES = [
        'Okafor', 'Bergman', 'Silva', 'Novak', 'Hayes', 'Kowalski', 'Moreau', 'Haddad', 'Larsen',
        'Petrov', 'Dubois', 'Oyelaran', 'Castillo', 'Ferreira', 'Johansson', 'Keita', 'Lindqvist',
        'Mensah', 'Nakamura', 'Oduya', 'Pereira', 'Quintero', 'Rahman', 'Santos', 'Toure',
        'Uzoma', 'Vidal', 'Winther', 'Xiong', 'Yilmaz', 'Zabala', 'Adeyemi', 'Bakker', 'Costa',
        'Delacroix', 'Eriksson', 'Fontaine', 'Garrido', 'Holm', 'Ibanez', 'Jarvis', 'Kallas',
        'Lefevre', 'Marchetti', 'Ngata', 'Osei', 'Prentice', 'Rousseau', 'Sorensen', 'Tanaka',
    ];

    public static function clubNames(int $count): array
    {
        $names = [];
        $places = self::CLUB_PLACE_WORDS;
        shuffle($places);
        $suffixes = self::CLUB_SUFFIXES;
        $i = 0;
        while (count($names) < $count) {
            $place = $places[$i % count($places)];
            $suffix = $suffixes[intdiv($i, count($places)) % count($suffixes)];
            $name = $place . ' ' . $suffix;
            if (!in_array($name, $names, true)) $names[] = $name;
            $i++;
            if ($i > 500) break; // safety valve, never hit in practice at this pool size
        }
        return $names;
    }

    public static function shortCode(string $clubName): string
    {
        $words = preg_split('/\s+/', trim($clubName));
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 2));
        }
        return strtoupper(substr($clubName, 0, 3));
    }

    public static function clubColors(int $index): array
    {
        return self::CLUB_COLORS[$index % count(self::CLUB_COLORS)];
    }

    public static function clubPattern(int $index): string
    {
        return self::PATTERNS[$index % count(self::PATTERNS)];
    }

    public static function playerName(array &$used): string
    {
        $attempts = 0;
        do {
            $first = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
            $last = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
            $name = $first . ' ' . $last;
            $attempts++;
        } while (in_array($name, $used, true) && $attempts < 200);
        $used[] = $name;
        return $name;
    }
}
