<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The short code of a provider is the first part of its report numbers. Providers that exist already get an abbreviation of their name.
        Schema::table('assessment_providers', function (Blueprint $table): void {
            $table->string('code', 10)->nullable();
        });

        $taken = [];

        foreach (DB::table('assessment_providers')->orderBy('id')->get(['id', 'name']) as $provider) {
            $code = $this->abbreviation((string) $provider->name, (int) $provider->id);

            if (in_array($code, $taken, true)) {
                $code = substr($code, 0, 10 - strlen((string) $provider->id)).$provider->id;
            }

            $taken[] = $code;
            DB::table('assessment_providers')->where('id', $provider->id)->update(['code' => $code]);
        }

        Schema::table('assessment_providers', function (Blueprint $table): void {
            $table->string('code', 10)->nullable(false)->change();
            $table->unique('code');
        });

        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        $connection->statement("ALTER TABLE \"{$prefix}assessment_providers\" ADD CONSTRAINT \"{$prefix}assessment_providers_code_check\" CHECK (\"code\" ~ '^[A-Z0-9]{2,10}$')");

        // The points of a drawing question are the highest score the psychologist can give it; drawings made before this had none, so they get 10.
        DB::table('assessment_questions')->where('type', 'drawing')->where('points', 0)->update(['points' => 10]);

        // What the psychologist decided about one drawing: a score (up to the points of the question) and a note.
        Schema::table('assessment_drawings', function (Blueprint $table): void {
            $table->decimal('score', 8, 2)->nullable();
            $table->text('note')->nullable();
        });

        // The report of one psychological attempt, made when the attempt is submitted. It is a draft (`pending_review`) until a psychologist of the provider
        // signs it: the signature stamps the number, the signing time and a copy of the psychologist's name and licence numbers. The `explanation` is the
        // plain-language text for the candidate; `review_notes` are the psychologist's notes on the drawings and the speed columns.
        Schema::create('assessment_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->unique()->constrained('assessment_attempts')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('assessment_providers')->restrictOnDelete();
            $table->string('status', 20);
            $table->text('body');
            $table->text('explanation')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->string('report_number', 40)->nullable();
            $table->unsignedSmallInteger('report_year')->nullable();
            $table->unsignedInteger('report_sequence')->nullable();
            $table->foreignId('signed_by_psychologist_id')->nullable()->constrained('assessment_psychologists')->restrictOnDelete();
            $table->string('signer_name', 150)->nullable();
            $table->string('signer_str_number', 60)->nullable();
            $table->string('signer_silp_number', 60)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'report_year', 'report_sequence']);
            $table->unique(['provider_id', 'report_number']);
            $table->index(['provider_id', 'status']);
        });

        $name = $prefix.'assessment_reports';
        $connection->statement("ALTER TABLE \"{$name}\" ADD CONSTRAINT \"{$name}_status_check\" CHECK (\"status\" IN ('pending_review', 'signed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_reports');

        Schema::table('assessment_drawings', function (Blueprint $table): void {
            $table->dropColumn(['score', 'note']);
        });

        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();
        $connection->statement("ALTER TABLE \"{$prefix}assessment_providers\" DROP CONSTRAINT IF EXISTS \"{$prefix}assessment_providers_code_check\"");

        Schema::table('assessment_providers', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }

    /** The initials of the words of the name, or the first letters when there is only one word; letters and digits only, 2 to 10 characters. */
    private function abbreviation(string $name, int $id): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = strtoupper(implode('', array_map(static fn (string $word): string => $word[0], $words)));
        $code = strlen($initials) >= 2 ? $initials : strtoupper(substr(implode('', $words), 0, 4));

        return strlen($code) >= 2 ? substr($code, 0, 10) : 'P'.$id;
    }
};
