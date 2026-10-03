<?php

namespace Tests\Feature\CandidateProfile;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

class ResumeApiTest extends CandidateTestCase
{
    private function makeResume(int $candidateId, string $name = 'CV', string $file = 'uploads/file/candidates/x.pdf'): int
    {
        return DB::table('candidate_resumes')->insertGetId([
            'candidate_id' => $candidateId, 'name' => $name, 'file' => $file, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_list_returns_only_own_resumes_newest_first(): void
    {
        $user = $this->actingCandidate();
        $mine = $this->makeCandidate($user);
        $other = $this->makeCandidate($this->makeUser());
        $first = $this->makeResume($mine, 'First');
        $second = $this->makeResume($mine, 'Second');
        $this->makeResume($other, 'Not mine');

        $data = $this->getJson('/api/candidate/resumes')->assertOk()->json('data');

        $this->assertSame([$second, $first], array_column($data, 'id'));
        $this->assertSame(['id', 'name', 'file', 'file_size'], array_keys($data[0]));
        $this->assertSame('0 MB', $data[0]['file_size']);
    }

    public function test_list_is_empty_for_company(): void
    {
        Sanctum::actingAs($this->makeUser('company'));

        $this->getJson('/api/candidate/resumes')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_show_own_resume(): void
    {
        $user = $this->actingCandidate();
        $id = $this->makeResume($this->makeCandidate($user), 'Mine');

        $this->getJson("/api/candidate/resumes/$id")->assertOk()
            ->assertJsonPath('data.message', 'Resume Retried Successfully!')
            ->assertJsonPath('data.data.id', $id)
            ->assertJsonPath('data.data.name', 'Mine')
            ->assertJsonStructure(['data' => ['data' => ['id', 'name', 'file', 'file_size', 'file_url']]]);
    }

    public function test_show_other_candidates_resume_is_not_found(): void
    {
        $this->actingCandidate();
        $foreign = $this->makeResume($this->makeCandidate($this->makeUser()));

        $this->getJson("/api/candidate/resumes/$foreign")->assertNotFound()->assertJsonStructure(['error']);
        $this->getJson('/api/candidate/resumes/999999999')->assertNotFound();
    }

    public function test_upload_resume(): void
    {
        $user = $this->actingCandidate();

        $response = $this->post('/api/candidate/upload-resume', [
            'name' => 'My CV', 'resume_file' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        $response->assertJsonPath('data.message', 'Resume uploaded Successfully!')
            ->assertJsonPath('data.data.name', 'My CV')
            ->assertJsonStructure(['data' => ['data' => ['id', 'candidate_id', 'name', 'file', 'created_at', 'updated_at', 'file_size', 'file_url']]]);

        $candidateId = DB::table('candidates')->where('user_id', $user->id)->value('id');
        $this->assertDatabaseHas('candidate_resumes', ['candidate_id' => $candidateId, 'name' => 'My CV', 'file' => $this->storage->stored[0]]);
        $this->assertStringContainsString('uploads/file/candidates/', $this->storage->stored[0]);
    }

    public function test_upload_resume_validation(): void
    {
        $this->actingCandidate();
        $headers = ['Accept' => 'application/json'];

        $this->post('/api/candidate/upload-resume', [], $headers)->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'resume_file']);

        $this->post('/api/candidate/upload-resume', [
            'name' => 'x', 'resume_file' => UploadedFile::fake()->create('cv.exe', 10, 'application/octet-stream'),
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('resume_file');

        $this->post('/api/candidate/upload-resume', [
            'name' => 'x', 'resume_file' => UploadedFile::fake()->create('cv.pdf', 6000, 'application/pdf'),
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('resume_file');

        $this->post('/api/candidate/upload-resume', [
            'name' => str_repeat('a', 101), 'resume_file' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'),
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->assertSame([], $this->storage->stored);
    }

    public function test_update_resume_name_and_file(): void
    {
        $user = $this->actingCandidate();
        $id = $this->makeResume($this->makeCandidate($user), 'Old', 'uploads/file/candidates/old.pdf');

        $this->postJson("/api/candidate/update-resume/$id", ['name' => 'Renamed'])->assertOk()
            ->assertJsonPath('data.message', 'Resume Updated Successfully!')
            ->assertJsonPath('data.data.name', 'Renamed')
            ->assertJsonPath('data.data.file', 'uploads/file/candidates/old.pdf');
        $this->assertSame([], $this->storage->deleted);

        $this->post("/api/candidate/update-resume/$id", [
            'name' => 'With file', 'resume_file' => UploadedFile::fake()->create('n.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.data.file', $this->storage->stored[0]);

        $this->assertDatabaseHas('candidate_resumes', ['id' => $id, 'name' => 'With file', 'file' => $this->storage->stored[0]]);
        $this->assertSame(['uploads/file/candidates/old.pdf'], $this->storage->deleted);
    }

    public function test_update_resume_validation_and_ownership(): void
    {
        $this->actingCandidate();
        $foreign = $this->makeResume($this->makeCandidate($this->makeUser()), 'Theirs');

        $this->postJson("/api/candidate/update-resume/$foreign", ['name' => 'Hacked'])->assertNotFound();
        $this->assertDatabaseHas('candidate_resumes', ['id' => $foreign, 'name' => 'Theirs']);

        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $own = $this->makeResume($this->makeCandidate($user), 'Mine');
        $this->postJson("/api/candidate/update-resume/$own", [])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->post("/api/candidate/update-resume/$own", [
            'name' => 'n', 'resume_file' => UploadedFile::fake()->create('n.txt', 10, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('resume_file');
        $this->assertSame([], $this->storage->stored);
        $this->assertDatabaseHas('candidate_resumes', ['id' => $own, 'name' => 'Mine']);
    }

    public function test_delete_resume(): void
    {
        $user = $this->actingCandidate();
        $id = $this->makeResume($this->makeCandidate($user), 'Bye', 'uploads/file/candidates/bye.pdf');

        $this->deleteJson("/api/candidate/delete-resume/$id")->assertOk()
            ->assertExactJson(['data' => ['message' => 'Resume Deleted Successfully!', 'status' => true]]);

        $this->assertDatabaseMissing('candidate_resumes', ['id' => $id]);
        $this->assertSame(['uploads/file/candidates/bye.pdf'], $this->storage->deleted);
    }

    public function test_delete_other_candidates_resume_is_not_found(): void
    {
        $this->actingCandidate();
        $foreign = $this->makeResume($this->makeCandidate($this->makeUser()));

        $this->deleteJson("/api/candidate/delete-resume/$foreign")->assertNotFound();
        $this->assertDatabaseHas('candidate_resumes', ['id' => $foreign]);
        $this->assertSame([], $this->storage->deleted);
    }

    public function test_company_cannot_manage_resumes(): void
    {
        Sanctum::actingAs($this->makeUser('company'));

        $this->getJson('/api/candidate/resumes/1')->assertForbidden();
        $this->postJson('/api/candidate/upload-resume', [])->assertForbidden();
        $this->deleteJson('/api/candidate/delete-resume/1')->assertForbidden();
    }
}
