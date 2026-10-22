<?php

use App\Actions\Auth\ResetAccountPassword;
use App\Actions\Auth\VerifyAccount;
use App\Actions\Projects\ManageProject;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

// Invoked only by the PostgreSQL concurrency test. Secrets arrive on stdin,
// never process arguments or stdout. This does not expose an application route.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || ! str_ends_with((string) config('database.connections.pgsql.database'), '_test')) {
    exit(2);
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
try {
    echo "ready\n";
    flush();
    if ($input['action'] === 'verify') {
        $user = User::findOrFail($input['user_id']);
        $app->make(VerifyAccount::class)->handle($user);
        echo "verified\n";
    } elseif ($input['action'] === 'project-create') {
        $app->make(ManageProject::class)->create(User::findOrFail($input['user_id']), $input['data']);
        echo "created\n";
    } elseif ($input['action'] === 'project-restore') {
        $project = Project::findOrFail($input['project_ids'][$input['worker']]);
        $app->make(ManageProject::class)->command(User::findOrFail($input['user_id']), $project->id, $project->version, 'restore');
        echo "restored\n";
    } else {
        $result = $app->make(ResetAccountPassword::class)->handle($input['credentials']);
        echo $result === Password::PASSWORD_RESET ? "reset\n" : "rejected\n";
    }
} catch (ValidationException) {
    echo "rejected\n";
} catch (Throwable) {
    echo "failed\n";
    exit(1);
}
