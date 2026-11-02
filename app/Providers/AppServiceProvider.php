<?php

namespace App\Providers;

use App\Services\Ai\AiClient;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\OllamaAiClient;
use App\Services\Ai\Rag\KnowledgeBase;
use App\Services\Ai\Rag\OllamaEmbedder;
use App\Models\Contract;
use App\Models\LaborParameter;
use App\Models\Settlement;
use App\Services\Ai\SimulatedAiClient;
use App\Services\NotificationCenter;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // AI_DRIVER in .env selects the provider; see docs/IA.md.
        $this->app->singleton(AiClient::class, fn () => match (config('ai.driver')) {
            'simulated' => new SimulatedAiClient,
            'ollama' => new OllamaAiClient(config('ai.providers.ollama')),
            default => new class implements AiClient
            {
                public function run(string $task, string $instructions, array $input, array $schema = []): array
                {
                    throw new AiUnavailable('El proveedor de IA «'.config('ai.driver').'» no está configurado.');
                }

                public function label(): string
                {
                    return 'Sin configurar';
                }
            },
        });
        // Semantic search only with Ollama and an embedding model; otherwise retrieval is BM25 alone.
        $this->app->singleton(KnowledgeBase::class, function () {
            $model = config('ai.driver') === 'ollama' ? config('ai.rag.embedding_model') : null;

            return new KnowledgeBase(config('ai.rag'), $model ? new OllamaEmbedder(config('ai.providers.ollama'), $model) : null);
        });
    }

    public function boot(): void
    {
        // Versioned so browsers pick up icons added to the sprite.
        View::share('iconSprite', asset('icons/hugeicons.svg').'?v='.@filemtime(public_path('icons/hugeicons.svg')));
        // Alerts follow the data they are derived from: any change makes every user re-sync on their next request.
        foreach ([Contract::class, Settlement::class, LaborParameter::class] as $model) {
            $model::saved(fn () => app(NotificationCenter::class)->refresh());
            $model::deleted(fn () => app(NotificationCenter::class)->refresh());
        }
        // The top bar bell starts with the real unread count; js/nav.js keeps it current.
        View::composer('layouts.app', function ($view) {
            if ($user = auth()->user()) {
                app(NotificationCenter::class)->sync($user);
                $view->with('unreadNotifications', $user->unreadNotifications()->count());
            }
        });
        ResetPassword::toMailUsing(fn ($user, string $token) => (new MailMessage)
            ->subject('Restablece tu contraseña · Liquidaciones')
            ->greeting('Hola, '.$user->name)
            ->line('Usa este enlace para crear o restablecer la contraseña de tu cuenta en Liquidaciones.')
            ->action('Crear contraseña nueva', route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()]))
            ->line('El enlace vence en '.config('auth.passwords.users.expire').' minutos. Si no lo pediste, ignora este correo: tu contraseña no cambia.')
            ->salutation('Liquidaciones · Gestión laboral'));
        Gate::before(function ($user, string $ability) {
            return $user->role === 'admin' ? true : null;
        });
    }
}
