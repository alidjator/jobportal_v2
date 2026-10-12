<?php

namespace Tests\Unit\Recruitment\Infrastructure;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Recruitment\Infrastructure\Video\CoturnIceServers;
use Src\Recruitment\Infrastructure\Video\HmacJoinTokenFactory;
use Src\Recruitment\Infrastructure\Video\LocalRecordingVolume;

final class VideoServicesTest extends TestCase
{
    public function test_a_turn_credential_is_the_keyed_hash_of_the_expiry_and_the_user(): void
    {
        $expires = new DateTimeImmutable('2026-10-12 12:30:00 UTC');
        $servers = (new CoturnIceServers(['turn:t.example.test:3478', 'turns:t.example.test:5349'], 'turn-secret'))->forUser(42, $expires);

        $username = $expires->getTimestamp().':42';
        $this->assertCount(1, $servers);
        $this->assertSame(['turn:t.example.test:3478', 'turns:t.example.test:5349'], $servers[0]->urls);
        $this->assertSame($username, $servers[0]->username);
        $this->assertSame(base64_encode(hash_hmac('sha1', $username, 'turn-secret', true)), $servers[0]->credential);
    }

    public function test_without_a_configured_server_the_browser_gets_none(): void
    {
        $expires = new DateTimeImmutable('2026-10-12 12:30:00 UTC');

        $this->assertSame([], (new CoturnIceServers([], 'secret'))->forUser(1, $expires));
        $this->assertSame([], (new CoturnIceServers(['turn:t.example.test'], null))->forUser(1, $expires));
        $this->assertSame([], (new CoturnIceServers(['turn:t.example.test'], ''))->forUser(1, $expires));
    }

    public function test_a_join_token_is_stable_and_differs_by_interview_room_and_user(): void
    {
        $factory = new HmacJoinTokenFactory('app-key');
        $token = $factory->make(1, 10, 7);

        $this->assertSame($token, $factory->make(1, 10, 7));
        $this->assertSame(64, strlen($token));
        $this->assertNotSame($token, $factory->make(2, 10, 7));
        $this->assertNotSame($token, $factory->make(1, 11, 7));
        $this->assertNotSame($token, $factory->make(1, 10, 8));
        $this->assertNotSame($token, (new HmacJoinTokenFactory('other-key'))->make(1, 10, 7));
    }

    public function test_a_display_name_is_signed_and_only_valid_for_its_interview_room_and_user(): void
    {
        $factory = new HmacJoinTokenFactory('app-key');
        $display = $factory->display(1, 10, 7);

        $this->assertMatchesRegularExpression('/^7\.[0-9a-f]{16}$/', $display);
        $this->assertSame($display, $factory->display(1, 10, 7));
        $this->assertSame(7, $factory->userOfDisplay($display, 1, 10));
        $this->assertNull($factory->userOfDisplay($display, 2, 10));
        $this->assertNull($factory->userOfDisplay($display, 1, 11));
        $this->assertNull($factory->userOfDisplay('8.'.substr($display, 2), 1, 10));
        $this->assertNull($factory->userOfDisplay('7.0000000000000000', 1, 10));
        $this->assertNull($factory->userOfDisplay('Mallory', 1, 10));
        $this->assertNull($factory->userOfDisplay('', 1, 10));
        $this->assertNull((new HmacJoinTokenFactory('other-key'))->userOfDisplay($display, 1, 10));
    }

    public function test_the_volume_opens_only_the_files_a_converter_writes_and_removes_what_belongs_to_a_recording(): void
    {
        $directory = sys_get_temp_dir().'/volume-test-'.getmypid();
        mkdir("{$directory}/ready", 0777, true);
        mkdir("{$directory}/raw/5", 0777, true);
        file_put_contents("{$directory}/ready/5.webm", 'video');
        file_put_contents("{$directory}/raw/5/a.mjr", 'raw');
        file_put_contents("{$directory}/secret.txt", 'secret');
        $volume = new LocalRecordingVolume('/recordings/', $directory);

        $this->assertSame('/recordings/raw/5', $volume->rawDirectory(5));

        $stream = $volume->open('ready/5.webm');
        $this->assertIsResource($stream);
        $this->assertSame('video', stream_get_contents($stream));
        fclose($stream);

        foreach (['../secret.txt', 'ready/../secret.txt', 'secret.txt', 'ready/5.exe', 'ready/x.webm', 'ready/6.webm', '/etc/passwd'] as $name) {
            $this->assertNull($volume->open($name), $name);
        }

        $volume->discard(5);
        $volume->discard(5);

        $this->assertFileDoesNotExist("{$directory}/ready/5.webm");
        $this->assertDirectoryDoesNotExist("{$directory}/raw/5");
        $this->assertFileExists("{$directory}/secret.txt");
        $this->assertNull((new LocalRecordingVolume('/recordings', null))->open('ready/5.webm'));

        unlink("{$directory}/secret.txt");
        rmdir("{$directory}/ready");
        rmdir("{$directory}/raw");
        rmdir($directory);
    }
}
