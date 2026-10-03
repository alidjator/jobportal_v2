<?php

namespace Src\Shared\Domain\Exceptions;

/** Aturan bisnis dilanggar (HTTP 400 `{error}` agar sama dengan aplikasi lama). */
final class BusinessRuleViolation extends DomainException {}
