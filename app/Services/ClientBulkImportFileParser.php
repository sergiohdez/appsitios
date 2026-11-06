<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use Throwable;

class ClientBulkImportFileParser
{
    private const CHUNK_SIZE = 1000;

    private const USER_HEADERS = [
        'name' => ['nombre', 'name'],
        'email' => ['correo', 'correoelectronico', 'email', 'emaildeacceso'],
        'role' => ['perfil', 'perfildeusuario', 'rol', 'role', 'profile'],
        'sites' => ['sitios', 'sitiosasignados', 'sites', 'assignedsites'],
    ];

    private const SITE_HEADERS = [
        'name' => ['nombre', 'name'],
        'code' => ['codigo', 'code'],
        'address' => ['direccion', 'address'],
        'timezone' => ['zonahoraria', 'timezone', 'time zone'],
    ];

    private const FIELD_LABELS = [
        'name' => 'Nombre',
        'email' => 'Correo electrónico',
        'role' => 'Perfil',
        'code' => 'Código',
        'address' => 'Dirección',
        'timezone' => 'Zona horaria',
    ];

    /**
     * @return array{has_headers: bool, records: list<array<string, mixed>>}
     */
    public function parse(UploadedFile $file, string $entity): array
    {
        $path = $file->getRealPath();
        $extension = mb_strtolower($file->getClientOriginalExtension());

        if ($path === false || ! is_readable($path)) {
            throw ValidationException::withMessages([
                'file' => 'No se pudo leer el archivo. Verifica que sea un CSV, XLS o XLSX válido y que no esté protegido con contraseña.',
            ]);
        }

        try {
            if ($extension === 'csv') {
                $reader = new Csv();
                $reader->setDelimiter($this->detectCsvDelimiter($path));
                $reader->setEnclosure('"');
                $reader->setEscapeCharacter('\\');
                $reader->setInputEncoding('UTF-8');
            } else {
                $reader = IOFactory::createReaderForFile($path);
            }

            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'file' => 'No se pudo leer el archivo. Verifica que sea un CSV, XLS o XLSX válido y que no esté protegido con contraseña.',
            ]);
        }

        if ($highestRow < 1) {
            $spreadsheet->disconnectWorksheets();
            throw ValidationException::withMessages(['file' => 'El archivo no contiene registros.']);
        }

        $positionalColumns = Coordinate::columnIndexFromString($highestColumn);
        $headers = $entity === 'users' ? self::USER_HEADERS : self::SITE_HEADERS;
        $requiredHeaders = $entity === 'users'
            ? ['name', 'email', 'role']
            : ['name', 'timezone'];

        $records = [];
        $hasHeaders = false;

        try {
            $firstRowData = $this->readRow($sheet, 1);

            $headerMap = $this->resolveHeaders($firstRowData, $headers);
            $hasHeaders = $headerMap !== [];

            if ($hasHeaders) {
                $headerMap = $this->requireHeaders($headerMap, $requiredHeaders, $entity);
            } else {
                $headerMap = $entity === 'users'
                    ? ['name' => 0, 'email' => 1, 'role' => 2, 'sites' => 3]
                    : ['name' => 0, 'code' => 1, 'address' => 2, 'timezone' => 3];

                $minimumColumns = $entity === 'users' ? 3 : 4;
                if ($positionalColumns < $minimumColumns) {
                    throw ValidationException::withMessages([
                        'file' => $entity === 'users'
                            ? 'El archivo debe tener al menos las columnas Nombre, correo electrónico y Perfil en ese orden.'
                            : 'El archivo debe tener al menos las columnas Nombre, código, dirección y zona horaria en ese orden.',
                    ]);
                }
            }

            $firstDataRow = $hasHeaders ? 2 : 1;

            for ($chunkStart = $firstDataRow; $chunkStart <= $highestRow; $chunkStart += self::CHUNK_SIZE) {
                $chunkEnd = min($chunkStart + self::CHUNK_SIZE - 1, $highestRow);
                foreach ($this->readChunk($sheet, $chunkStart, $chunkEnd, $headerMap) as $record) {
                    $records[] = $record;
                }
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
            gc_collect_cycles();
        }

        if ($records === []) {
            throw ValidationException::withMessages(['file' => 'El archivo no contiene registros para importar.']);
        }

        return ['has_headers' => $hasHeaders, 'records' => $records];
    }

    /**
     * @param  array<string, int>  $headerMap
     * @param  list<string>  $requiredHeaders
     * @return array<string, int>
     */
    private function requireHeaders(array $headerMap, array $requiredHeaders, string $entity): array
    {
        $missing = array_values(array_diff($requiredHeaders, array_keys($headerMap)));

        if ($missing !== []) {
            $labels = collect($missing)
                ->map(fn (string $field): string => self::FIELD_LABELS[$field] ?? $field)
                ->implode(', ');

            throw ValidationException::withMessages([
                'file' => 'El archivo tiene encabezados pero falta la columna obligatoria: '.$labels.'. Complétala e inténtalo de nuevo.',
            ]);
        }

        return $headerMap;
    }

    /**
     * Read one physical row as a 0-based sparse array without materialising the sheet.
     *
     * @return array<int, mixed>
     */
    private function readRow($sheet, int $rowNumber): array
    {
        $rawRow = [];

        foreach ($sheet->getRowIterator($rowNumber, $rowNumber) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $rawRow[Coordinate::columnIndexFromString($cell->getColumn()) - 1] = $cell->getValue();
            }
            break;
        }

        return $rawRow;
    }

    /**
     * Stream one chunk of rows (at most CHUNK_SIZE) without materialising the
     * whole sheet as a single 2-D array. Each chunk is normalised, appended to
     * the result and released before the next chunk starts.
     *
     * @param  array<string, int>  $headerMap
     * @return list<array<string, mixed>>
     */
    private function readChunk($sheet, int $chunkStart, int $chunkEnd, array $headerMap): array
    {
        $records = [];
        $physicalRow = $chunkStart - 1;

        foreach ($sheet->getRowIterator($chunkStart, $chunkEnd) as $row) {
            $physicalRow++;
            $rawRow = [];
            foreach ($row->getCellIterator() as $cell) {
                $rawRow[Coordinate::columnIndexFromString($cell->getColumn()) - 1] = $cell->getValue();
            }

            $record = $this->normaliseRow($rawRow, $headerMap, $physicalRow);
            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @param  array<int, mixed>  $rawRow
     * @param  array<string, int>  $headerMap
     * @return array<string, mixed>|null
     */
    private function normaliseRow(array $rawRow, array $headerMap, int $physicalRow): ?array
    {
        $normalized = [];
        foreach ($headerMap as $field => $column) {
            $normalized[$field] = $this->cellValue($rawRow[$column] ?? '');
        }

        if (collect($normalized)->every(fn (string $value): bool => $value === '')) {
            return null;
        }

        $normalized['source_row'] = $physicalRow;

        return $normalized;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, list<string>>  $aliases
     * @return array<string, int>
     */
    private function resolveHeaders(array $row, array $aliases): array
    {
        $normalized = [];

        foreach ($row as $index => $value) {
            $key = $this->normalizeHeader($this->cellValue($value));

            if ($key === '') {
                continue;
            }

            foreach ($aliases as $field => $names) {
                if (in_array($key, array_map($this->normalizeHeader(...), $names), true)) {
                    $normalized[$field] ??= $index;
                }
            }
        }

        return $normalized;
    }

    private function normalizeHeader(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::ascii(mb_strtolower(trim($value)))) ?? '';
    }

    private function cellValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return trim((string) $value);
    }

    private function detectCsvDelimiter(string|false $path): string
    {
        if ($path === false || ! is_readable($path)) {
            return ',';
        }

        $handle = fopen($path, 'rb');
        $sample = $handle === false ? false : fgets($handle);

        if (is_resource($handle)) {
            fclose($handle);
        }

        if (! is_string($sample)) {
            return ',';
        }

        $counts = [];

        foreach ([',', ';', "\t"] as $delimiter) {
            $counts[$delimiter] = count(str_getcsv($sample, $delimiter, '"', '\\'));
        }

        arsort($counts);

        return reset($counts) > 1 ? (string) array_key_first($counts) : ',';
    }
}
