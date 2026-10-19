const fs = require('node:fs');
const path = require('node:path');

for (const name of ['index', 'show']) {
    const viewPath = path.join(__dirname, '..', `resources/views/templates/nocturne/user/table_games/${name}.blade.php`);
    const source = fs.readFileSync(viewPath, 'utf8');
    let script = source.match(/<script>([\s\S]*?)<\/script>/)?.[1];
    if (!script) throw new Error(`Table game ${name} script was not found.`);
    script = script.replaceAll('@json($catalog)', '[]').replaceAll("@json(gs('cur_sym'))", '"$"').replaceAll("{{ url('/user/tables') }}", '"/user/tables"');
    new Function(script);
}
process.stdout.write('Table game lobby and browser scripts passed syntax checks.\n');
