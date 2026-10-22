<?php

use App\Actions\Selections\ManageSelection;
use App\Models\AccountAccess;
use App\Models\CatalogueEntry;
use App\Models\CatalogueRevision;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Tests\Support\SelectionFixture;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || ! str_ends_with((string) config('database.connections.pgsql.database'), '_test')) {
    exit(2);
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! preg_match('/\Abrowser-progress-[a-z0-9-]+@example\.test\z/', $input['email'] ?? '')) {
    exit(2);
}
try {
    Queue::fake();
    (new class
    {
        use SelectionFixture;

        public function run(array $input): void
        {
            if ($input['action'] === 'seed') {
                $project = $this->selectionProject(3);
                $project->photographer->update(['email' => $input['email'], 'password' => 'BrowserProgressPassword123']);
            } else {
                $owner = User::where('email', $input['email'])->firstOrFail();
                $project = Project::where('user_id', $owner->id)->firstOrFail();
            }
            $request = Request::create('/', 'POST');
            $request->setLaravelSession(app('session')->driver());
            $selections = app(ManageSelection::class);
            if ($input['action'] === 'seed') {
                foreach (['one', 'two'] as $file) {
                    $selections->client($request, $project->share_token, $this->selectionPayload($project, $file));
                }
            } elseif ($input['action'] === 'confirm') {
                $selections->client($request, $project->share_token,
                    array_diff_key($this->selectionPayload($project), ['file_id' => true, 'selected' => true]), true);
            } elseif ($input['action'] === 'reopen') {
                $selections->reopen($project->photographer, $project->id, ['epoch' => $project->selection_epoch, 'version' => $project->selection_version]);
            } elseif ($input['action'] === 'remove-original') {
                $selections->client($request, $project->share_token, $this->selectionPayload($project, 'one', false));
                $revision = $project->modules()->where('type', 'selection')->value('published_revision_id');
                CatalogueEntry::where('revision_id', $revision)->where('file_id', 'one')->delete();
            } elseif ($input['action'] === 'expire') {
                AccountAccess::whereKey($project->user_id)->update(['access_ends_at' => now()]);
            } elseif ($input['action'] === 'failed-refresh') {
                $module = $project->modules()->where('type', 'selection')->firstOrFail();
                $revision = CatalogueRevision::findOrFail($module->published_revision_id)->replicate();
                $revision->state = 'failed';
                $revision->save();
                $module->update(['requested_revision_id' => $revision->id]);
            } else {
                throw new RuntimeException('Unsupported fixture action');
            }
        }
    })->run($input);
    echo "ok\n";
} catch (Throwable) {
    // Do not emit credentials, capability links or exception payloads.
    exit(1);
}
