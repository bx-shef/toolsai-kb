<?php declare(strict_types=1);

/**
 * Import\SourceReader: статьи пакета с диска (fixtures/source).
 */

require_once __DIR__.'/assert.php';
require_once __DIR__.'/stub/autoload.php';

use Shef\ToolsAiKb\Import\SourceReader;

$read = SourceReader::read(__DIR__.'/fixtures/source');
Check::group('состав и порядок');
Check::same('файлы по пути, подкаталоги, без скрытых и не-.md', array_map(static fn($a) => $a->path, $read['articles']), ['10-intro.md', '20-quota.md', 'setup/install.md']);
Check::same('заголовок', $read['articles'][1]->title, 'Квота расхода');
Check::same('ошибок нет', $read['errors'], []);

Check::group('ошибки');
$missing = SourceReader::read(__DIR__.'/fixtures/nope');
Check::same('нет каталога — ошибка, статей нет', [$missing['articles'], count($missing['errors'])], [[], 1]);

$tmp = sys_get_temp_dir().'/shef-toolsaikb-'.getmypid();
@mkdir($tmp.'/pack', 0777, true);
file_put_contents($tmp.'/secret.md', '# Чужое');
file_put_contents($tmp.'/pack/ok.md', '# Своё');
@symlink($tmp.'/secret.md', $tmp.'/pack/link.md');
@symlink($tmp.'/pack', $tmp.'/pack/loop');
$linked = SourceReader::read($tmp.'/pack');
Check::same('ссылка наружу не читается, каталог-ссылка не обходится', array_map(static fn($a) => $a->path, $linked['articles']), ['ok.md']);
Check::same('о ссылке — ошибка', count($linked['errors']), 1);
@unlink($tmp.'/pack/link.md');
@unlink($tmp.'/pack/loop');
@unlink($tmp.'/pack/ok.md');
@unlink($tmp.'/secret.md');
@rmdir($tmp.'/pack');
@rmdir($tmp);

Check::finish();
