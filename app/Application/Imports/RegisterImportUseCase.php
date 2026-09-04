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
     * Idempotently registers an Import. Returns the existing Import with no
     * side effects if (supplier, external_import_id) has already been seen:
     * `$import->wasRecentlyCreated` tells the caller whether to dispatch the Job.
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
