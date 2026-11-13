<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    /** A program's specialized subject: only for the programs it is linked to. */
    public const MAJOR = 'major';

    /** General education and PE (Ethics, Purposive Communication, PE...): open to every program. */
    public const MINOR = 'minor';

    public const CATEGORIES = [self::MAJOR => 'Major', self::MINOR => 'Minor'];

    protected $fillable = ['code', 'title', 'units', 'category', 'description', 'is_active'];

    protected $attributes = ['category' => self::MINOR];

    protected function casts(): array
    {
        return [
            'units' => 'decimal:1',
            'is_active' => 'boolean',
        ];
    }

    public function classOfferings(): HasMany
    {
        return $this->hasMany(ClassOffering::class);
    }

    /** Programs a major subject belongs to (minor subjects have none). */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class)->withTimestamps()->orderBy('code');
    }

    /** Subjects that must be completed before this one (e.g. Programming 2 needs Programming 1). */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_prerequisites', 'subject_id', 'prerequisite_id')
            ->withTimestamps()->orderBy('code');
    }

    /** Subjects that list this one as a prerequisite. */
    public function requiredFor(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_prerequisites', 'prerequisite_id', 'subject_id')->orderBy('code');
    }

    /** Would requiring $prerequisiteId for this subject create a loop (A needs B, B needs A)? */
    public function wouldLoop(int $prerequisiteId): bool
    {
        if ($prerequisiteId === $this->id) {
            return true;
        }

        // Walk down from the proposed prerequisite; finding this subject means a loop.
        $seen = [];
        $queue = [$prerequisiteId];
        while ($queue !== []) {
            $id = array_shift($queue);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $next = \Illuminate\Support\Facades\DB::table('subject_prerequisites')->where('subject_id', $id)->pluck('prerequisite_id')->map(fn ($v) => (int) $v)->all();
            if (in_array($this->id, $next, true)) {
                return true;
            }
            array_push($queue, ...$next);
        }

        return false;
    }

    public function isMajor(): bool
    {
        return $this->category === self::MAJOR;
    }

    /** Minor subjects are open to every program. */
    public function isGeneral(): bool
    {
        return ! $this->isMajor();
    }

    /** May a student of this program take the subject? */
    public function isOpenTo(?int $programId): bool
    {
        return $this->isGeneral() || ($programId !== null && $this->programs->contains('id', $programId));
    }

    /** "BSCS, BSIT" for a major, "All programs" for a minor. */
    public function programsLabel(): string
    {
        return $this->isGeneral() ? 'All programs' : $this->programs->pluck('code')->implode(', ');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Minor';
    }

    /** Subjects a program may take: its own majors plus every minor. */
    public function scopeOpenToProgram(Builder $query, ?int $programId): Builder
    {
        return $query->where(fn ($q) => $q->where('category', self::MINOR)
            ->when($programId, fn ($q2) => $q2->orWhereHas('programs', fn ($p) => $p->where('programs.id', $programId))));
    }
}
