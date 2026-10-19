const fs = require('node:fs');
const path = require('node:path');

const viewPath = path.join(__dirname, '..', 'resources/views/templates/nocturne/user/table_games/show.blade.php');
const source = fs.readFileSync(viewPath, 'utf8');
const script = source.match(/<script>([\s\S]*?)<\/script>/)?.[1];
if (!script) throw new Error('Table game view script was not found.');
new Function(script);
process.stdout.write('Table game browser script syntax passed.\n');
