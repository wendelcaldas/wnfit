<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organizacao extends Model
{
    use HasFactory;

    public const MODULE_DEFAULTS = [
        'students' => true,
        'workouts' => true,
        'finance' => true,
        'schedule' => true,
        'messaging' => false,
    ];

    protected $table = 'organizacoes';

    protected function casts(): array
    {
        return ['onboarding_profile' => 'array', 'modules' => 'array'];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nome_fantasia',
        'razao_social',
        'slug',
        'tipo',
        'onboarding_profile',
        'email_contato',
        'telefone_contato',
        'ativa',
    ];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organizacao_usuario')
            ->using(OrganizacaoUsuario::class)
            ->withPivot(['papel', 'status', 'telefone'])
            ->withTimestamps();
    }

    public function alunos(): HasMany
    {
        return $this->hasMany(Aluno::class);
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(Unidade::class);
    }

    public function planos(): HasMany
    {
        return $this->hasMany(Plano::class);
    }

    public function treinos(): HasMany
    {
        return $this->hasMany(Treino::class);
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class);
    }

    public function mensagemConfiguracao(): HasOne
    {
        return $this->hasOne(MensagemConfiguracao::class);
    }

    public function hasMessagingModule(): bool
    {
        return $this->hasModule('messaging');
    }

    public function moduleAccess(): array
    {
        $defaults = self::MODULE_DEFAULTS;
        $defaults['messaging'] = in_array($this->slug, config('services.messaging.module_organization_slugs', []), true);

        return array_replace($defaults, $this->modules ?? []);
    }

    public function hasModule(string $module): bool
    {
        return $this->moduleAccess()[$module] ?? false;
    }
}
