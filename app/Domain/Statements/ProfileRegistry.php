<?php

namespace App\Domain\Statements;

use App\Domain\Statements\Contracts\BankProfileContract;
use App\Domain\Statements\Profiles\IciciStyleXlsxProfile;
use App\Domain\Statements\Profiles\KotakCsvProfile;

/**
 * Holds every registered BankProfileContract and implements the frozen
 * Hybrid profile-selection state machine (PHASE_7_DECISION_PACKAGE.md
 * section 5, Open Decision 11): content-based detection only, exactly-one
 * match auto-selects, zero matches is a hard rejection, multiple matches
 * must never auto-parse.
 */
class ProfileRegistry
{
    /** @var array<int, BankProfileContract> */
    private array $profiles;

    /**
     * @param  array<int, BankProfileContract>|null  $profiles  Overridable for
     *                                                          tests exercising the Ambiguous-Match branch of the Hybrid
     *                                                          selection state machine, which the two real, currently-supported
     *                                                          profiles (one per file format) cannot naturally trigger against
     *                                                          each other. Production code always uses the default.
     */
    public function __construct(?array $profiles = null)
    {
        $this->profiles = $profiles ?? [
            new KotakCsvProfile,
            new IciciStyleXlsxProfile,
        ];
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array<int, BankProfileContract> every profile whose format matches
     *                                         and whose content-detection signature matches
     */
    public function detect(string $fileType, array $rows): array
    {
        return array_values(array_filter(
            $this->profiles,
            fn (BankProfileContract $profile) => $profile->fileType() === $fileType && $profile->matches($rows)
        ));
    }

    public function find(string $key): ?BankProfileContract
    {
        foreach ($this->profiles as $profile) {
            if ($profile->key() === $key) {
                return $profile;
            }
        }

        return null;
    }
}
