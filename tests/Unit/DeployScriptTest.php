<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Runs deploy.sh in a temporary directory with stubbed php/composer/npm binaries
 * that only record how they were called.
 */
function runDeployScript(array $env = [], bool $withEnvFile = true, ?string $failingTool = null, bool $firstDeploy = false): array
{
    $dir = sys_get_temp_dir().'/deploy-test-'.uniqid();
    File::makeDirectory($dir.'/bin', recursive: true);
    File::copy(base_path('deploy.sh'), $dir.'/deploy.sh');

    if (! $firstDeploy) {
        File::makeDirectory($dir.'/vendor');
        File::put($dir.'/vendor/autoload.php', '<?php');
    }

    if ($withEnvFile) {
        File::put($dir.'/.env', 'APP_ENV=production');
    }

    foreach (['php', 'composer', 'npm'] as $tool) {
        $exit = $tool === $failingTool ? 'exit 1' : 'exit 0';
        File::put($dir."/bin/$tool", "#!/usr/bin/env bash\necho \"$tool \$*\" >> \"$dir/calls.log\"\n$exit\n");
        chmod($dir."/bin/$tool", 0755);
    }

    $result = Process::path($dir)->env(array_merge([
        'PHP_BIN' => "$dir/bin/php",
        'COMPOSER_BIN' => "$dir/bin/composer",
        'NPM_BIN' => "$dir/bin/npm",
    ], $env))->run(['bash', 'deploy.sh']);

    $calls = File::exists("$dir/calls.log")
        ? array_values(array_filter(explode("\n", File::get("$dir/calls.log"))))
        : [];
    $calls = array_map(fn ($line) => str_replace("$dir/bin/", '', $line), $calls);

    File::deleteDirectory($dir);

    return [$result, $calls];
}

it('is valid bash', function () {
    expect(Process::run(['bash', '-n', base_path('deploy.sh')])->successful())->toBeTrue();
});

it('runs all deployment steps in the right order', function () {
    [$result, $calls] = runDeployScript();

    expect($result->successful())->toBeTrue($result->errorOutput())
        ->and($calls)->toBe([
            'php artisan down --retry=30',
            'php composer install --no-dev -o --no-interaction --prefer-dist',
            'npm ci --include=dev --no-audit --no-fund',
            'npm run build',
            'php artisan migrate --force',
            'php artisan db:seed --class=RolesAndPermissionsSeeder --force',
            'php artisan db:seed --class=MailTemplateSeeder --force',
            'php artisan config:cache',
            'php artisan route:cache',
            'php artisan view:cache',
            'php artisan event:cache',
            'php artisan queue:restart',
            'php artisan up',
        ]);
});

it('can skip the frontend build', function () {
    [$result, $calls] = runDeployScript(['SKIP_ASSETS' => '1']);

    expect($result->successful())->toBeTrue()
        ->and(implode("\n", $calls))->not->toContain('npm');
});

it('refuses to deploy without an .env file', function () {
    [$result, $calls] = runDeployScript(withEnvFile: false);

    expect($result->failed())->toBeTrue()
        ->and($result->errorOutput())->toContain('.env is missing')
        ->and($calls)->toBe([]);
});

it('leaves maintenance mode when a step fails', function () {
    [$result, $calls] = runDeployScript(failingTool: 'npm');

    expect($result->failed())->toBeTrue()
        ->and($calls)->toBe([
            'php artisan down --retry=30',
            'php composer install --no-dev -o --no-interaction --prefer-dist',
            'npm ci --include=dev --no-audit --no-fund',
            'php artisan up',
        ]);
});

it('skips the maintenance mode on the very first deployment', function () {
    [$result, $calls] = runDeployScript(firstDeploy: true);

    expect($result->successful())->toBeTrue($result->errorOutput())
        ->and($calls)->not->toContain('php artisan down --retry=30')
        ->and($calls)->toContain('php artisan migrate --force');
});
