<?php

namespace Src\Identity\Domain;

/**
 * Semua method mengembalikan array atribut user (tanpa password/remember_token, dengan `image_url`),
 * sama dengan serialisasi model User aplikasi lama.
 */
interface UserRepository
{
    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array;

    /** @return array<string,mixed>|null null jika email tak dikenal atau password salah */
    public function findByCredentials(string $email, string $password): ?array;

    /** @return array<string,mixed>|null */
    public function findByFirebaseUid(string $uid): ?array;

    public function findIdByEmail(string $email): ?int;

    public function emailExists(string $email): bool;

    public function usernameExists(string $username): bool;

    /**
     * Membuat user beserta data turunannya (profil candidate/company + contact info) dan
     * menerapkan status awal sesuai setting auto activation.
     *
     * @return array<string,mixed>
     */
    public function create(NewAccount $account): array;

    public function changePassword(int $userId, string $plainPassword): void;
}
