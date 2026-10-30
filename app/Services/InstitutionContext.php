<?php

namespace App\Services;

use App\Models\Institution;

class InstitutionContext
{
    private ?int $institutionId = null;

    private bool $systemAdministrator = false;

    public function set(?int $institutionId, bool $systemAdministrator = false): void
    {
        $this->institutionId = $institutionId;
        $this->systemAdministrator = $systemAdministrator;
    }

    public function id(): ?int
    {
        return $this->institutionId;
    }

    public function isSystemAdministrator(): bool
    {
        return $this->systemAdministrator;
    }

    public function institution(): ?Institution
    {
        return $this->institutionId ? Institution::find($this->institutionId) : null;
    }

    public function runFor(?int $institutionId, callable $callback): mixed
    {
        $previousId = $this->institutionId;
        $previousSystemAdministrator = $this->systemAdministrator;
        $this->set($institutionId, $previousSystemAdministrator);

        try {
            return $callback();
        } finally {
            $this->set($previousId, $previousSystemAdministrator);
        }
    }
}
