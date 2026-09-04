<?php

namespace App\Application\Imports;

use App\Application\Imports\Ports\ImportRepository;
use App\Application\Imports\Ports\SupplierRepository;
use App\Infrastructure\Persistence\Eloquent\Models\Import;
use App\Infrastructure\Persistence\Eloquent\Models\ImportStatus;

class RegisterImportUseCase
{
    public function __construct(
        private readonly SupplierRepository $suppliers,
        private readonly ImportRepository $imports,
    ) {}

    /**
     * Ідемпотентно реєструє Import — C1. Повертає існуючий Import без побічних
     * ефектів, якщо (supplier, external_import_id) вже зустрічались:
     * `$import->wasRecentlyCreated` каже викликачу, чи ставити Job у чергу.
     */
    public function handle(string $supplierCode, string $externalImportId, string $sentAt): Import
    {
        $supplier = $this->suppliers->findByCode($supplierCode);

        if ($supplier === null) {
            throw new \InvalidArgumentException("Unknown supplier: {$supplierCode}");
        }

        $existing = $this->imports->findBySupplierAndExternalImportId($supplier, $externalImportId);

        if ($existing !== null) {
            return $existing;
        }

        return $this->imports->create([
            'supplier_id' => $supplier->id,
            'external_import_id' => $externalImportId,
            'sent_at' => $sentAt,
            'status' => ImportStatus::Pending,
        ]);
    }
}
