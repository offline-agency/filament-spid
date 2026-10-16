<?php

use Illuminate\Support\Facades\Artisan;

/*
 * The documentation is part of what ships: these tests keep it in step with
 * the code and with itself.
 */

function docRoot(): string
{
    return dirname(__DIR__);
}

/**
 * @return list<string> repository-relative paths of the Markdown documents
 */
function markdownDocuments(): array
{
    $files = ['README.md', 'SECURITY.md', 'CONTRIBUTING.md', 'CHANGELOG.md', 'resources/boost/skills/spid-integration/SKILL.md'];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(docRoot().'/docs', FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $relative = substr($file->getPathname(), strlen(docRoot()) + 1);
        if ($file->getExtension() === 'md' && ! str_starts_with($relative, 'docs/superpowers/')) {
            $files[] = $relative;
        }
    }
    sort($files);

    return $files;
}

/**
 * The shell commands of a snippet, without comments and blank lines.
 *
 * @return list<string>
 */
function snippetCommands(string $snippet): array
{
    return array_values(array_filter(array_map('trim', explode("\n", $snippet)), fn (string $line) => $line !== '' && ! str_starts_with($line, '#')));
}

it('embeds the tested quickstart in the README verbatim', function () {
    $readme = file_get_contents(docRoot().'/README.md');
    preg_match('/<!-- quickstart:begin[^>]*-->\n```bash\n(.*?)```\n<!-- quickstart:end -->/s', $readme, $match);

    expect($match[1] ?? null)->toBe(file_get_contents(docRoot().'/docs/snippets/quickstart.sh'));
});

it('gives the Italian guide the same quickstart commands', function () {
    preg_match('/```bash\n(# Nella root.*?)```/s', file_get_contents(docRoot().'/docs/it/README.md'), $match);

    expect(snippetCommands($match[1] ?? ''))->toBe(snippetCommands(file_get_contents(docRoot().'/docs/snippets/quickstart.sh')));
});

it('documents only commands and options that exist', function (string $command, array $options) {
    $definition = Artisan::all()[$command]->getDefinition();

    foreach ($options as $option) {
        expect($definition->hasOption($option))->toBeTrue("{$command} has no --{$option}");
    }
})->with([
    ['filament-spid:install', ['panel', 'input', 'dry-run', 'migrate']],
    ['filament-spid:doctor', ['json', 'strict']],
    ['filament-spid:metadata:validate', ['url', 'file', 'profile', 'report', 'json', 'strict', 'tool', 'ca-file', 'timeout']],
]);

it('links only to files that exist', function (string $document) {
    $content = file_get_contents(docRoot().'/'.$document);
    preg_match_all('/\]\(([^)\s]+)\)/', $content, $links);
    expect($links[1])->toBeArray();

    foreach ($links[1] as $link) {
        if (preg_match('#^(https?:|mailto:|\#)#', $link) || str_starts_with($link, '../../contributors')) {
            continue;
        }
        $path = explode('#', $link)[0];
        expect(file_exists(dirname(docRoot().'/'.$document).'/'.$path))->toBeTrue("{$document} links to missing {$link}");
    }
})->with(fn () => markdownDocuments());

it('never shows a canAccessPanel that lets everyone in', function (string $document) {
    $content = file_get_contents(docRoot().'/'.$document);

    expect($content)->not->toMatch('/function canAccessPanel\([^)]*\)[^{]*\{\s*return true;/');
})->with(fn () => markdownDocuments());

it('documents every filament-spid configuration key', function () {
    $reference = file_get_contents(docRoot().'/docs/configuration.md');

    foreach (array_keys(require docRoot().'/config/filament-spid.php') as $key) {
        expect($reference)->toContain("`{$key}");
    }
});

it('puts in the .env example only variables the code reads', function () {
    $config = file_get_contents(docRoot().'/config/filament-spid.php');
    preg_match_all('/^#?\s*([A-Z_]+)=/m', file_get_contents(docRoot().'/docs/snippets/env.example'), $variables);

    foreach ($variables[1] as $variable) {
        if ($variable === 'SESSION_SECURE_COOKIE') {
            continue; // Laravel's own config/session.php
        }
        expect($config)->toContain("env('{$variable}'");
    }
});

it('ships the Laravel Boost guidelines and skill in the Composer archive', function (string $path) {
    expect(file_exists(docRoot().'/'.$path))->toBeTrue();

    $attributes = trim((string) shell_exec('cd '.escapeshellarg(docRoot()).' && git check-attr export-ignore -- '.escapeshellarg($path)));
    expect($attributes)->toEndWith('export-ignore: unspecified');
})->with([
    'resources/boost/guidelines/core.blade.php',
    'resources/boost/skills/spid-integration/SKILL.md',
    'examples/setup.public.json',
    'examples/setup.private.json',
]);

it('gives the Boost skill the frontmatter Boost requires', function () {
    $skill = file_get_contents(docRoot().'/resources/boost/skills/spid-integration/SKILL.md');

    expect($skill)->toMatch('/^---\nname: spid-integration\ndescription: .+\n---\n/');
});

it('keeps the Boost resources free of instructions that disable security controls', function () {
    $guidance = file_get_contents(docRoot().'/resources/boost/guidelines/core.blade.php')
        .file_get_contents(docRoot().'/resources/boost/skills/spid-integration/SKILL.md');

    expect($guidance)->not->toContain('withoutMiddleware')
        ->and($guidance)->not->toContain('audit.ignore"')
        ->and($guidance)->not->toMatch('/always recommend/i')
        ->and($guidance)->toContain('Never return `true` for everyone');
});

it('does not claim SPID accreditation, certification or official status', function (string $document) {
    $content = strtolower(file_get_contents(docRoot().'/'.$document));

    expect($content)->not->toMatch('/\b(agid[_ -]validated|certified by agid|official filament plugin|is an official)\b/');
})->with(fn () => markdownDocuments());

it('ships the screenshots it shows', function () {
    foreach (['login-light.png', 'login-dark.png', 'login-mobile.png'] as $image) {
        expect(getimagesize(docRoot().'/docs/images/'.$image))->not->toBeFalse();
    }
});
