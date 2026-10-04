<?php

namespace Src\Identity\Domain;

interface AdminRepository
{
    public function findById(int $id): ?Admin;

    public function findByEmail(string $email): ?Admin;

    /** Inserts a new admin or updates an existing one, and returns the stored aggregate (with its id). */
    public function save(Admin $admin): Admin;
}
