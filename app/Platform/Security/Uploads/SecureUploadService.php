<?php

namespace App\Platform\Security\Uploads;

use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Storage\TenantStorageManager;
use App\Platform\Tenancy\TenantContext;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SecureUploadService
{
    /**
     * Whitelist of allowed MIME types and their trusted file extensions.
     *
     * @var array<string, list<string>>
     */
    protected const ALLOWED_MIME_TYPES = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'application/pdf' => ['pdf'],
        'application/dicom' => ['dcm'],
        'application/octet-stream' => ['dcm'],
    ];

    /**
     * Default maximum file size: 10 MB (10 * 1024 * 1024 bytes).
     */
    protected const DEFAULT_MAX_SIZE_BYTES = 10485760;

    public function __construct(
        protected TenantContext $tenantContext,
        protected TenantStorageManager $storageManager
    ) {}

    /**
     * Validate, randomize filename and securely store an uploaded file.
     *
     * @param  string  $category  (e.g., 'radiographies', 'documents', 'consents')
     * @return array{
     *     stored_path: string,
     *     filename: string,
     *     mime_type: string,
     *     size_bytes: int,
     *     original_name: string
     * }
     */
    public function store(
        UploadedFile $file,
        string $category = 'documents',
        ?int $maxSizeBytes = null,
        ?Tenant $tenant = null
    ): array {
        $resolvedTenant = $tenant ?? $this->tenantContext->requireCurrent();
        $limit = $maxSizeBytes ?? self::DEFAULT_MAX_SIZE_BYTES;

        // 1. Verify basic upload integrity
        if (! $file->isValid()) {
            throw new InvalidArgumentException('El archivo subido no es válido o está corrupto.');
        }

        // 2. Validate max size
        if ($file->getSize() > $limit) {
            $mb = round($limit / (1024 * 1024), 2);
            throw new InvalidArgumentException("El archivo excede el tamaño máximo permitido de {$mb} MB.");
        }

        // 3. Inspect real MIME type via magic bytes
        $realMime = $this->detectRealMimeType($file->getRealPath());

        if (! array_key_exists($realMime, self::ALLOWED_MIME_TYPES)) {
            throw new InvalidArgumentException("Tipo de archivo no permitido: {$realMime}.");
        }

        // 4. Resolve safe extension
        $allowedExtensions = self::ALLOWED_MIME_TYPES[$realMime];
        $clientExtension = strtolower($file->getClientOriginalExtension());
        $safeExtension = in_array($clientExtension, $allowedExtensions, true)
            ? $clientExtension
            : $allowedExtensions[0];

        // 5. Generate randomized UUID filename
        $safeFilename = Str::uuid()->toString().'.'.$safeExtension;
        $relativePath = "uploads/{$category}/{$safeFilename}";

        // 6. Write to isolated private tenant storage
        $fileContents = file_get_contents($file->getRealPath());
        if ($fileContents === false) {
            throw new InvalidArgumentException('No se pudo leer el contenido del archivo.');
        }

        $this->storageManager->put($relativePath, $fileContents, $resolvedTenant);

        return [
            'stored_path' => $this->storageManager->path($relativePath, $resolvedTenant),
            'filename' => $safeFilename,
            'mime_type' => $realMime,
            'size_bytes' => $file->getSize(),
            'original_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Inspect file signature (magic bytes) using PHP finfo.
     */
    protected function detectRealMimeType(string $realPath): string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($realPath);

        return $mime ?: 'application/octet-stream';
    }
}
