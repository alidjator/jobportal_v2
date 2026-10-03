<?php

namespace Tests\Feature\CandidateProfile;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Infrastructure\Persistence\UserModel;
use Src\Shared\Application\Contracts\FileStorage;
use Tests\FeatureTestCase;

abstract class CandidateTestCase extends FeatureTestCase
{
    protected FakeFileStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = new FakeFileStorage;
        $this->app->instance(FileStorage::class, $this->storage);
    }

    protected function makeUser(string $role = 'candidate', string $password = 'secret-pass-1'): UserModel
    {
        $key = Str::lower(Str::random(10));
        $id = DB::table('users')->insertGetId([
            'name' => 'User '.$key,
            'username' => 'u'.$key,
            'email' => $key.'@example.test',
            'password' => Hash::make($password),
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return UserModel::findOrFail($id);
    }

    /** @param  array<string,mixed>  $overrides */
    protected function makeCandidate(UserModel $user, array $overrides = []): int
    {
        return DB::table('candidates')->insertGetId($overrides + [
            'user_id' => $user->id,
            'name' => $user->username,
            'profile_mark' => 0,
            'education_mark' => 0,
            'experience_mark' => 0,
            'resume_mark' => 0,
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function actingCandidate(): UserModel
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        return $user;
    }

    protected function makeExperience(string $name): int
    {
        $id = DB::table('experiences')->insertGetId(['slug' => 'exp-'.Str::random(10), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('experience_translations')->insert(['experience_id' => $id, 'name' => $name, 'locale' => 'en']);

        return $id;
    }

    protected function makeEducation(string $name): int
    {
        $id = DB::table('education')->insertGetId(['slug' => Str::slug($name).'-'.Str::random(5), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('education_translations')->insert(['education_id' => $id, 'name' => $name, 'locale' => 'en']);

        return $id;
    }

    protected function makeSkill(string $name): int
    {
        $id = DB::table('skills')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        DB::table('skill_translations')->insert(['skill_id' => $id, 'name' => $name, 'locale' => 'en']);

        return $id;
    }

    protected function makeLanguage(string $name): int
    {
        return DB::table('candidate_languages')->insertGetId(['name' => $name, 'slug' => Str::slug($name).'-'.Str::random(5)]);
    }
}
