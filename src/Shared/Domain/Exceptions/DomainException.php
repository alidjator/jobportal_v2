<?php

namespace Src\Shared\Domain\Exceptions;

use RuntimeException;

/**
 * Base untuk semua error domain/aplikasi. Layer HTTP memetakannya ke status
 * (lihat bootstrap/app.php); domain sendiri tidak tahu apa pun soal HTTP.
 */
abstract class DomainException extends RuntimeException {}
