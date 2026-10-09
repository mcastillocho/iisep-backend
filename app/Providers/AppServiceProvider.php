<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerDatabaseMacros();
    }

    /**
     * Register custom database macros.
     */
    protected function registerDatabaseMacros(): void
    {
        Blueprint::macro('pgEnum', function (string $column, string $enumType, array $allowed = []) {
            /** @var Blueprint $this */
            if (DB::getDriverName() === 'pgsql') {
                return $this->addColumn('pgEnum', $column, ['pgEnum' => $enumType]);
            }

            return $this->enum($column, $allowed);
        });

        PostgresGrammar::macro('typePgEnum', function (Fluent $column) {
            return $column->get('pgEnum');
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
