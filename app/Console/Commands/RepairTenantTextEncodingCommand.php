<?php

namespace App\Console\Commands;

use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairTenantTextEncodingCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'tenant:repair-text-encoding
                            {tenant : Tenant slug to inspect}
                            {--apply : Persist the detected repairs}';

    /**
     * @var string
     */
    protected $description = 'Repair UTF-8 text that was stored as Windows-1252 mojibake in a tenant database';

    /**
     * Execute the console command.
     */
    public function handle(TenantContext $tenantContext): int
    {
        $tenant = Tenant::query()->where('slug', $this->argument('tenant'))->first();

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $changes = 0;
        $examples = [];

        $tenantContext->execute($tenant, function () use ($apply, &$changes, &$examples): void {
            $connection = DB::connection('tenant');
            $tables = $connection->select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%'");

            $repair = function () use ($connection, $apply, &$changes, &$examples, $tables): void {
                foreach ($tables as $table) {
                    $tableName = $table->name;
                    $columns = $connection->select("pragma table_info('".str_replace("'", "''", $tableName)."')");
                    $primaryKey = collect($columns)->first(fn (object $column): bool => (int) $column->pk > 0);
                    $textColumns = collect($columns)->filter(fn (object $column): bool => $this->isTextColumn($column->type));

                    if ($primaryKey === null || $textColumns->isEmpty()) {
                        continue;
                    }

                    $columnNames = $textColumns->pluck('name')->all();
                    $rows = $connection->table($tableName)
                        ->select(array_merge([$primaryKey->name], $columnNames))
                        ->get();

                    foreach ($rows as $row) {
                        $updates = [];

                        foreach ($columnNames as $columnName) {
                            $value = $row->{$columnName};

                            if (! is_string($value)) {
                                continue;
                            }

                            $repaired = $this->repairStoredValue($value);

                            if ($repaired === $value) {
                                continue;
                            }

                            $updates[$columnName] = $repaired;
                            $examples[] = "{$tableName}.{$columnName} [{$primaryKey->name}={$row->{$primaryKey->name}}]";
                        }

                        if ($updates === []) {
                            continue;
                        }

                        $changes += count($updates);

                        if ($apply) {
                            $connection->table($tableName)
                                ->where($primaryKey->name, $row->{$primaryKey->name})
                                ->update($updates);
                        }
                    }
                }
            };

            if ($apply) {
                $connection->transaction($repair);
            } else {
                $repair();
            }
        });

        foreach (array_slice($examples, 0, 10) as $example) {
            $this->line($example);
        }

        $verb = $apply ? 'Repaired' : 'Detected';
        $this->info("{$verb} {$changes} malformed text values for tenant [{$tenant->slug}].");

        if (! $apply && $changes > 0) {
            $this->comment('Run the command again with --apply to persist these changes.');
        }

        return self::SUCCESS;
    }

    private function isTextColumn(string $type): bool
    {
        return str_contains(strtolower($type), 'char')
            || str_contains(strtolower($type), 'text')
            || str_contains(strtolower($type), 'clob')
            || str_contains(strtolower($type), 'json');
    }

    private function hasMojibake(string $value): bool
    {
        return preg_match('/(?:\\x{00C3}.|\\x{00C2}.|\\x{00E2}..)/u', $value) === 1;
    }

    private function repairStoredValue(string $value): string
    {
        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            $changed = false;
            $decoded = $this->repairDecodedValue($decoded, $changed);

            if ($changed) {
                return json_encode(
                    $decoded,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                );
            }
        }

        return $this->repairString($value);
    }

    private function repairDecodedValue(mixed $value, bool &$changed): mixed
    {
        if (is_string($value)) {
            $repaired = $this->repairString($value);
            $changed = $changed || $repaired !== $value;

            return $repaired;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->repairDecodedValue($item, $changed);
            }
        }

        return $value;
    }

    private function repairString(string $value): string
    {
        if (! $this->hasMojibake($value)) {
            return $value;
        }

        $repaired = mb_convert_encoding($value, 'Windows-1252', 'UTF-8');

        return mb_check_encoding($repaired, 'UTF-8') ? $repaired : $value;
    }
}
